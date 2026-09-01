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
  const startedAt = Date.now();
  console.log(`[Stream Proxy] Starting proxyRequest to ${targetUrl}`);
  try {
    const result = await proxyRequest(event, targetUrl, { headers });
    console.log(
      `[Stream Proxy] proxyRequest resolved after ${Date.now() - startedAt}ms, response status: ${event.node.res.statusCode}`,
    );
    return result;
  } catch (error) {
    console.error(
      `[Stream Proxy] proxyRequest threw after ${Date.now() - startedAt}ms:`,
      error instanceof Error ? error.message : error,
    );
    throw error;
  }
});
