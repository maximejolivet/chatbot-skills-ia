// Dedicated route: the generic catch-all proxy (server/api/[...path].ts)
// buffers the whole response through $fetch before returning it, which
// would defeat the point of an SSE endpoint (the browser would only see
// the response once the backend had already finished streaming it, or
// worse, get a mis-typed body). proxyRequest instead pipes the backend's
// response straight through without buffering. Being a more specific
// route, Nitro matches this before falling back to the catch-all.
export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  const apiUrl = config.public.apiUrl || 'http://chatbot-symfony:8000';
  const id = getRouterParam(event, 'id');

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

  // Intermittent connectivity blip between Vercel and o2switch on this
  // specific streamed-POST route (h3's proxyRequest throws "Bad Gateway"
  // within ~2s, well before any response bytes reach the client -- other,
  // non-streamed routes through server/api/[...path].ts never see this).
  // One retry is safe precisely because it fails this fast/early: nothing
  // has been written to event.node.res yet, so a second attempt is a clean
  // do-over, not a double response.
  const maxAttempts = 2;
  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    try {
      return await proxyRequest(event, targetUrl, { headers });
    } catch (error) {
      const isLastAttempt = attempt === maxAttempts;
      // h3 wraps the underlying fetch failure as a 502 H3Error, and
      // Node/undici's "fetch failed" TypeError wraps ITS OWN root cause
      // another level down (e.g. ECONNRESET, cert error) -- walk the whole
      // .cause chain instead of stopping one level in.
      const chain: string[] = [];
      let current: unknown = error;
      for (let i = 0; i < 5 && current; i++) {
        if (current instanceof Error) {
          const code = (current as NodeJS.ErrnoException).code;
          chain.push(`${current.name}: ${current.message}${code ? ` (code: ${code})` : ''}`);
          current = current.cause;
        } else {
          chain.push(JSON.stringify(current));
          break;
        }
      }
      console.error(
        `[Stream Proxy] proxyRequest attempt ${attempt}/${maxAttempts} failed. Cause chain:`,
        chain.join(' -> '),
      );
      if (isLastAttempt || event.node.res.headersSent) {
        throw error;
      }
    }
  }
});
