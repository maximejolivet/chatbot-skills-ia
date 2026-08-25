// Dedicated + cached: GET /api/ai_agents is identical for every visitor
// (public read-only, admin-managed via /admin/ai-agents -- see
// backend/src/Entity/AiAgent.php) and gets hit on every widget mount
// (composables/useChatbot.ts::fetchAgents, to resolve the default active
// agent). Caching it here avoids a real backend round-trip -- and its own
// DB query -- on every single page load for data that only ever changes
// through the admin backoffice. A more specific route than the generic
// catch-all (server/api/[...path].ts), so Nitro matches this first; the
// public API for this resource is read-only (no POST/PATCH/DELETE, see
// that entity), so there's nothing that could ever need this cache
// invalidated early.
export default defineCachedEventHandler(
  async () => {
    const config = useRuntimeConfig();
    const apiUrl = config.public.apiUrl || 'http://chatbot-symfony:8000';

    const headers: Record<string, string> = {
      'Content-Type': 'application/ld+json',
      Accept: 'application/ld+json',
    };
    // Same service-account auth as the generic proxy (see server/api/[...path].ts).
    if (config.adminUsername) {
      const credentials = Buffer.from(`${config.adminUsername}:${config.adminPassword}`).toString(
        'base64',
      );
      headers['Authorization'] = `Basic ${credentials}`;
    }

    return $fetch(`${apiUrl}/api/ai_agents`, { headers });
  },
  {
    maxAge: 300,
    name: 'ai_agents',
    getKey: () => 'public',
  },
);
