// Dedicated route: the generic catch-all proxy (server/api/[...path].ts)
// buffers the whole response through $fetch before returning it, which
// would defeat the point of an SSE endpoint (the browser would only see
// the response once the backend had already finished streaming it, or
// worse, get a mis-typed body). This route streams the backend's response
// through manually instead.
//
// NOT using h3's proxyRequest/sendProxy here (despite being the "obvious"
// h3 tool for this): it reads the request body as a raw Buffer
// (readRawBody(event, false)) and hands that Buffer straight to fetch as
// the body. When undici needs to silently retry the send (e.g. a pooled
// keep-alive socket to chatbot.jolivetmaxime.fr turned out to be stale),
// it tries to resend that same Buffer's ArrayBuffer -- which undici had
// already detached transferring it the first time -- and throws
// "Cannot perform ArrayBuffer.prototype.slice on a detached ArrayBuffer",
// wrapped by h3 into a generic, uninformative "502 Bad Gateway" every
// time. This hit ~30-50% of real requests in prod (confirmed via
// vercel logs walking the full error.cause chain), independent of
// Vercel's function duration -- a first attempt could fail this way just
// as often as a retry. Reading the body as a string instead (readRawBody's
// default encoding) sidesteps it entirely: strings aren't
// transferable/detachable, so undici can always re-encode and resend one
// safely.
export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const apiUrl = config.public.apiUrl || 'http://chatbot-symfony:8000';
  const id = getRouterParam(event, 'id');
  const body = await readRawBody(event);

  const headers: Record<string, string> = {
    'Content-Type': getHeader(event, 'content-type') || 'application/json',
  };

  // Same service-account auth as the generic proxy (see server/api/[...path].ts)
  // -- the Symfony "api" firewall requires HTTP Basic on every request.
  if (config.adminUsername) {
    const credentials = Buffer.from(`${config.adminUsername}:${config.adminPassword}`).toString(
      'base64',
    );
    headers['Authorization'] = `Basic ${credentials}`;
  }

  const targetUrl = `${apiUrl}/api/conversations/${id}/stream`;
  const upstream = await fetch(targetUrl, { method: 'POST', headers, body });

  event.node.res.statusCode = upstream.status;
  for (const [key, value] of upstream.headers.entries()) {
    // Same exclusions as h3's sendProxy: these describe the upstream fetch
    // response's own framing, not what we're about to write to the client.
    if (key === 'content-encoding' || key === 'content-length') continue;
    event.node.res.setHeader(key, value);
  }

  if (!upstream.body) {
    event.node.res.end();
    return;
  }

  const reader = upstream.body.getReader();
  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      event.node.res.write(value);
    }
  } finally {
    event.node.res.end();
  }
});
