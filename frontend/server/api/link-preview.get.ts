import dns from 'node:dns/promises';
import net from 'node:net';

// Fetches a page's <title>/favicon server-side for MessageBubble.vue's link
// preview cards -- CSP's img-src ('self' data:, see nuxt.config.ts) blocks
// hotlinking an arbitrary external favicon directly from the browser, so
// the favicon bytes are inlined as a data: URI instead.
//
// This is a public URL-fetch relay by nature (anyone can hit this route
// directly with any `url`, not just via a real chat message that happens
// to contain a link) -- the mitigations below close the obvious SSRF paths
// (private/loopback targets, redirects to one) but this doesn't defend
// against DNS rebinding (a hostname that resolves to a public IP at
// validation time and a private one at connection time): accepted
// tradeoff for a personal-scale project, same category of "reasonable but
// not complete" defense as the CSP itself.
const FETCH_TIMEOUT_MS = 5000;
const MAX_HTML_BYTES = 200_000;
const MAX_FAVICON_BYTES = 100_000;
const MAX_IMAGE_BYTES = 3_000_000;
const MAX_REDIRECTS = 3;
const USER_AGENT = 'Mozilla/5.0 (compatible; ChatbotLinkPreview/1.0)';

function isPrivateIp(ip: string): boolean {
  if (net.isIP(ip) === 4) {
    const [a, b] = ip.split('.').map(Number);
    if (a === 10 || a === 127 || a === 0) return true;
    if (a === 169 && b === 254) return true;
    if (a === 172 && b >= 16 && b <= 31) return true;
    if (a === 192 && b === 168) return true;
    return false;
  }
  if (net.isIP(ip) === 6) {
    const lower = ip.toLowerCase();
    return (
      '::1' === lower ||
      lower.startsWith('fe80:') ||
      lower.startsWith('fc') ||
      lower.startsWith('fd')
    );
  }
  return true; // not a literal IP and not resolved yet -- fail closed
}

async function assertPublicHost(hostname: string): Promise<void> {
  if ('localhost' === hostname.toLowerCase()) throw new Error('blocked host');

  if (net.isIP(hostname)) {
    if (isPrivateIp(hostname)) throw new Error('blocked host');
    return;
  }

  const records = await dns.lookup(hostname, { all: true });
  if (0 === records.length || records.some((r) => isPrivateIp(r.address))) {
    throw new Error('blocked host');
  }
}

interface FetchedResource {
  bytes: Uint8Array;
  contentType: string;
}

// Validates the host and follows redirects manually (re-validating each hop
// -- `redirect: 'follow'` would let a public URL 30x its way to a private
// one after the initial check already passed), stopping right before
// reading the body -- the caller picks how many bytes are worth reading
// once it knows the actual content-type (an HTML page's <head> needs far
// fewer bytes than an inline image preview does, see MAX_HTML_BYTES vs
// MAX_IMAGE_BYTES below).
async function resolvePublicResponse(startUrl: URL): Promise<Response | null> {
  let current = startUrl;

  for (let hop = 0; hop <= MAX_REDIRECTS; hop++) {
    await assertPublicHost(current.hostname);

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);
    let response: Response;
    try {
      response = await fetch(current, {
        signal: controller.signal,
        redirect: 'manual',
        headers: { 'User-Agent': USER_AGENT },
      });
    } catch {
      return null;
    } finally {
      clearTimeout(timeout);
    }

    if ([301, 302, 303, 307, 308].includes(response.status)) {
      const location = response.headers.get('location');
      if (!location) return null;

      const next = new URL(location, current);
      if (!['http:', 'https:'].includes(next.protocol)) return null;
      current = next;
      continue;
    }

    if (!response.ok || !response.body) return null;

    return response;
  }

  return null; // too many redirects
}

async function readBounded(response: Response, maxBytes: number): Promise<FetchedResource> {
  const reader = response.body!.getReader();
  const chunks: Uint8Array[] = [];
  let total = 0;
  while (total < maxBytes) {
    const { done, value } = await reader.read();
    if (done) break;
    chunks.push(value);
    total += value.byteLength;
  }
  reader.cancel().catch(() => {});

  const bytes = new Uint8Array(total);
  let offset = 0;
  for (const chunk of chunks) {
    bytes.set(chunk, offset);
    offset += chunk.byteLength;
  }

  return { bytes, contentType: response.headers.get('content-type') || '' };
}

// Convenience wrapper for the simple case (favicon: always the same small
// cap regardless of content-type) -- the main handler below uses
// resolvePublicResponse/readBounded separately instead, to size the read by
// content-type.
async function fetchPublicResource(
  startUrl: URL,
  maxBytes: number,
): Promise<FetchedResource | null> {
  const response = await resolvePublicResponse(startUrl);
  return response ? readBounded(response, maxBytes) : null;
}

function decodeHtmlEntities(text: string): string {
  return text
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;/g, "'")
    .replace(/&#x27;/g, "'");
}

function extractTitle(html: string): string | null {
  const ogMatch =
    html.match(/<meta[^>]+property=["']og:title["'][^>]+content=["']([^"']*)["']/i) ??
    html.match(/<meta[^>]+content=["']([^"']*)["'][^>]+property=["']og:title["']/i);
  if (ogMatch) return decodeHtmlEntities(ogMatch[1]).trim() || null;

  const titleMatch = html.match(/<title[^>]*>([^<]*)<\/title>/i);
  return titleMatch ? decodeHtmlEntities(titleMatch[1]).trim() || null : null;
}

function extractFaviconHref(html: string): string | null {
  const iconMatch =
    html.match(/<link[^>]+rel=["'](?:shortcut icon|icon)["'][^>]+href=["']([^"']+)["']/i) ??
    html.match(/<link[^>]+href=["']([^"']+)["'][^>]+rel=["'](?:shortcut icon|icon)["']/i);
  return iconMatch ? iconMatch[1] : null;
}

export default defineCachedEventHandler(
  async (event) => {
    const rawUrl = String(getQuery(event).url || '');

    let target: URL;
    try {
      target = new URL(rawUrl);
    } catch {
      throw createError({ statusCode: 400, statusMessage: 'Invalid URL' });
    }
    if (!['http:', 'https:'].includes(target.protocol)) {
      throw createError({ statusCode: 400, statusMessage: 'Unsupported protocol' });
    }

    const response = await resolvePublicResponse(target).catch(() => null);
    const responseType = response?.headers.get('content-type') || '';

    // A link that points directly at an image (a screenshot, a diagram...)
    // is shown as the image itself -- no title/favicon to fetch, one
    // request instead of two.
    if (response && responseType.startsWith('image/')) {
      const image = await readBounded(response, MAX_IMAGE_BYTES);
      if (image.bytes.byteLength === 0) {
        return {
          url: target.href,
          domain: target.hostname,
          title: null,
          faviconDataUri: null,
          imageDataUri: null,
        };
      }
      const contentType = image.contentType.split(';')[0].trim() || 'image/jpeg';
      const imageDataUri = `data:${contentType};base64,${Buffer.from(image.bytes).toString('base64')}`;
      return {
        url: target.href,
        domain: target.hostname,
        title: null,
        faviconDataUri: null,
        imageDataUri,
      };
    }

    if (!response || !responseType.includes('text/html')) {
      response?.body?.cancel().catch(() => {});
      return {
        url: target.href,
        domain: target.hostname,
        title: null,
        faviconDataUri: null,
        imageDataUri: null,
      };
    }

    const page = await readBounded(response, MAX_HTML_BYTES);

    const html = new TextDecoder().decode(page.bytes);
    const title = extractTitle(html);
    const faviconHref = extractFaviconHref(html) ?? '/favicon.ico';

    let faviconDataUri: string | null = null;
    try {
      const faviconUrl = new URL(faviconHref, target);
      const favicon = await fetchPublicResource(faviconUrl, MAX_FAVICON_BYTES);
      if (favicon && favicon.bytes.byteLength > 0) {
        const contentType = favicon.contentType.split(';')[0].trim() || 'image/x-icon';
        faviconDataUri = `data:${contentType};base64,${Buffer.from(favicon.bytes).toString('base64')}`;
      }
    } catch {
      faviconDataUri = null;
    }

    return { url: target.href, domain: target.hostname, title, faviconDataUri, imageDataUri: null };
  },
  {
    maxAge: 86400,
    name: 'link-preview',
    getKey: (event) => String(getQuery(event).url || ''),
  },
);
