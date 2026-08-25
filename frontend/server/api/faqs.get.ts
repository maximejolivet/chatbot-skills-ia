// Dedicated + cached: GET /api/faqs is identical for every visitor (public,
// admin-managed, no per-visitor variation -- see backend/src/Entity/Faq.php)
// and gets hit on every widget mount (composables/useFaqs.ts, both the
// landing-page hero bar and the chat panel's empty state). Caching it here
// avoids a real backend round-trip -- and its own DB query -- on every
// single page load for data that only ever changes through /admin/faqs.
// A more specific route than the generic catch-all (server/api/[...path].ts),
// so Nitro matches this first; the FAQ API is read-only (no write
// operations exist, see that entity), so there's nothing that could ever
// need this cache invalidated early.
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

    return $fetch(`${apiUrl}/api/faqs`, { headers });
  },
  {
    maxAge: 300,
    name: 'faqs',
    getKey: () => 'public',
  },
);
