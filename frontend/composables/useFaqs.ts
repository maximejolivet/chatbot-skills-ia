// Suggested conversation-starter questions, shown on the /chat empty state
// (Chatbot.vue) and the landing-page hero bar (HeroChatBar.vue). Backed by
// GET /api/faqs (see backend/src/Entity/Faq.php) instead of a hardcoded
// list, so an admin can manage them from /admin/faqs without a deploy.
//
// useState (not a plain ref): both call sites can mount independently
// (HeroChatBar on `/`, Chatbot on `/chat`) and should share one fetch
// instead of each hitting the API on their own mount.
export const useFaqs = () => {
  const suggestedQuestions = useState<string[]>('faq-suggested-questions', () => []);
  const hasFetched = useState<boolean>('faq-suggested-questions-fetched', () => false);

  const fetchSuggestedQuestions = async () => {
    if (hasFetched.value) return;
    hasFetched.value = true;

    try {
      // Same reasoning as useChatbot.ts::fetchAgents -- this is a regular
      // API Platform GET, which only accepts application/ld+json.
      // `highlighted` is the JSON-LD name of Faq::$isHighlighted (API
      // Platform strips the `is` prefix from boolean getters, same as
      // `isActive` -- already excluded server-side -- becoming `active`).
      // The API itself returns every active FAQ regardless of highlight
      // status (it may back other surfaces later); only this specific
      // consumer -- suggested conversation starters -- narrows to the
      // ones an admin curated for that purpose.
      const response = await $fetch<{
        member: Array<{ question: string; highlighted: boolean }>;
      }>('/api/faqs', {
        method: 'GET',
        credentials: 'include',
        headers: { 'Content-Type': 'application/ld+json' },
      });
      suggestedQuestions.value = (response?.member ?? [])
        .filter((faq) => faq.highlighted)
        .map((faq) => faq.question);
    } catch (error) {
      console.error('Erreur lors de la récupération des questions suggérées:', error);
    }
  };

  return {
    suggestedQuestions: computed(() => suggestedQuestions.value),
    fetchSuggestedQuestions,
  };
};
