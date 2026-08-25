// Least-privilege allowlist of the *only* backend routes this proxy will
// forward. This proxy authenticates every request as the real admin
// account (see below), regardless of who's actually asking -- by design, so
// the public widget never shows a login prompt. That means every resource
// whose access control assumes "authenticated == trusted admin" (the
// `security: "is_granted('ROLE_ADMIN')")` on Workflow/Document/
// AiProviderConfig, and the OwnershipVoter's unconditional ROLE_ADMIN
// bypass on Conversation/WorkflowExecution) silently becomes public the
// moment it's reachable through this proxy. Found during a security audit
// (2026-08-24): without this allowlist, GET /api/conversations and GET
// /api/workflow_executions were both fully readable anonymously through
// this exact proxy (every visitor's name/messages, every booking's
// recruiter email). Default-deny: a route not listed here is rejected
// before any backend call, not forwarded and left to the backend to sort
// out.
const ALLOWED_ROUTES: Array<{ method: string; pattern: RegExp }> = [
  { method: 'GET', pattern: /^chat\/llm-status$/ },
  { method: 'POST', pattern: /^chat\/quick-send$/ },
  { method: 'GET', pattern: /^chat\/embedding-status$/ },
  { method: 'POST', pattern: /^chat\/follow-up-questions$/ },
  { method: 'POST', pattern: /^conversations$/ }, // create only -- GET (list) would leak every visitor's conversations
  { method: 'GET', pattern: /^conversations\/\d+\/messages$/ },
  { method: 'POST', pattern: /^conversations\/\d+\/messages$/ },
  { method: 'PATCH', pattern: /^conversations\/\d+\/messages\/[^/]+\/feedback$/ },
  { method: 'GET', pattern: /^faqs$/ }, // also served by the dedicated cached route; kept here as a harmless fallback
  { method: 'GET', pattern: /^ai_agents$/ },
  { method: 'GET', pattern: /^health$/ },
];

const isAllowedRoute = (method: string, path: string): boolean =>
  ALLOWED_ROUTES.some((route) => route.method === method.toUpperCase() && route.pattern.test(path));

export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig();
  // Utiliser le nom de conteneur Docker pour communiquer entre conteneurs
  const apiUrl = config.public.apiUrl || 'http://chatbot-symfony:8000';

  // Récupérer le chemin depuis l'URL (tout ce qui suit /api/)
  const url = getRequestURL(event);
  const pathMatch = url.pathname.match(/^\/api\/(.+)$/);
  const path = pathMatch ? pathMatch[1] : '';

  if (!isAllowedRoute(event.method, path)) {
    throw createError({ statusCode: 404, statusMessage: 'Not Found' });
  }

  // Get the full path including query string
  const query = getQuery(event);
  const queryString = new URLSearchParams(query as Record<string, string>).toString();
  const fullPath = queryString ? `${path}?${queryString}` : path;

  const targetUrl = `${apiUrl}/api/${fullPath}`;

  // Get request body if present
  const body = await readBody(event).catch(() => null);

  // Log détaillé de l'appel API
  console.log('='.repeat(80));
  console.log('[Nuxt API Proxy] Appel API détecté');
  console.log(`  Méthode: ${event.method}`);
  console.log(`  URL d'origine: ${url.pathname}${url.search || ''}`);
  console.log(`  apiUrl (config): ${apiUrl}`);
  console.log(`  Chemin extrait: ${path}`);
  console.log(`  Query params: ${JSON.stringify(query)}`);
  console.log(`  URL reconstruite (targetUrl): ${targetUrl}`);
  if (body) {
    const bodyPreview =
      typeof body === 'object'
        ? JSON.stringify(body).substring(0, 200) + (JSON.stringify(body).length > 200 ? '...' : '')
        : String(body).substring(0, 200) + (String(body).length > 200 ? '...' : '');
    console.log(`  Body: ${bodyPreview}`);
  }
  console.log('='.repeat(80));

  // Forward the client's own Content-Type -- API Platform's regular CRUD
  // endpoints (e.g. POST /api/conversations) only accept application/ld+json,
  // while the custom controllers (quick-send, messages) parse raw
  // application/json themselves. Hardcoding one value here broke whichever
  // didn't match. Accept always mirrors it (never the browser's own Accept
  // header, which ofetch defaults to "application/json") for the same
  // reason -- API Platform's content negotiation 406s a response format it
  // doesn't recognize, e.g. "application/json" on a ld+json-only resource.
  const contentType = getHeader(event, 'content-type') || 'application/json';
  const headers: Record<string, string> = {
    'Content-Type': contentType,
    Accept: contentType,
  };

  // Forward cookies (harmless -- the "api" firewall is stateless)
  const cookie = getHeader(event, 'cookie');
  if (cookie) {
    headers['Cookie'] = cookie;
  }

  // The Symfony API requires HTTP Basic auth (stateless "api" firewall) --
  // authenticate as a service account on behalf of the public chat widget,
  // so end users never see a login prompt.
  if (config.adminUsername) {
    const credentials = Buffer.from(`${config.adminUsername}:${config.adminPassword}`).toString(
      'base64',
    );
    headers['Authorization'] = `Basic ${credentials}`;
  }

  const startTime = Date.now();
  try {
    const response = await $fetch(targetUrl, {
      method: event.method,
      headers,
      body: body || undefined,
      credentials: 'include',
    });
    const duration = Date.now() - startTime;

    console.log(`[Nuxt API Proxy] ✅ Réponse reçue en ${duration}ms`);
    console.log(`  Status: 200 OK`);
    if (response && typeof response === 'object') {
      const responsePreview =
        JSON.stringify(response).substring(0, 200) +
        (JSON.stringify(response).length > 200 ? '...' : '');
      console.log(`  Réponse: ${responsePreview}`);
    }
    console.log('='.repeat(80));

    return response;
  } catch (error: any) {
    const duration = Date.now() - startTime;
    console.error(`[Nuxt API Proxy] ❌ Erreur après ${duration}ms`);
    console.error(`  Status: ${error.statusCode || 500}`);
    console.error(`  Message: ${error.statusMessage || error.message || 'Unknown error'}`);
    console.error(`  Data: ${error.data ? JSON.stringify(error.data).substring(0, 200) : 'N/A'}`);
    console.error('='.repeat(80));

    throw createError({
      statusCode: error.statusCode || 500,
      statusMessage: error.statusMessage || 'Proxy error',
      data: error.data || error.message,
    });
  }
});
