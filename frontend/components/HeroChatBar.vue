<template>
  <div class="flex w-full flex-col items-center">
    <form
      class="flex w-full items-center gap-2 rounded-full border border-border bg-card p-2 pl-5 shadow-lg shadow-foreground/5 transition-shadow focus-within:shadow-xl"
      @submit="onSubmit"
    >
      <svg
        class="h-5 w-5 shrink-0 text-muted-foreground"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
        />
      </svg>
      <input
        v-model="question"
        type="text"
        :placeholder="$t('heroChatBar.placeholder')"
        class="min-w-0 flex-1 border-0 bg-transparent py-2.5 text-base text-foreground placeholder-muted-foreground focus:outline-none focus:ring-0 sm:text-sm"
      />
      <button
        type="submit"
        :disabled="!question.trim()"
        :aria-label="$t('heroChatBar.send')"
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary font-semibold text-primary-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-card disabled:cursor-not-allowed disabled:opacity-40"
      >
        <svg class="h-4 w-4 rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
          />
        </svg>
      </button>
    </form>

    <div class="mt-3 flex flex-wrap justify-center gap-2">
      <button
        v-for="suggestion in suggestedQuestions"
        :key="suggestion"
        type="button"
        class="rounded-full bg-card px-4 py-3 text-sm font-medium text-card-foreground shadow-sm shadow-foreground/5 transition-shadow hover:shadow-md"
        @click="askSuggestion(suggestion)"
      >
        {{ suggestion }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
const question = ref('');

// Written directly (not via useChatbot()) so mounting this bar on the
// landing page doesn't also trigger useChatbot's onMounted side effects
// (fetchAgents/checkLlmStatus/restoreConversation) before the visitor has
// even navigated to /chat.
const pendingMessage = useState<string | null>('chatbot-pending-message', () => null);

// Same source as the /chat empty state (see Chatbot.vue) -- both pull from
// the backend FAQ list via useFaqs() instead of a hardcoded list.
const { suggestedQuestions, fetchSuggestedQuestions } = useFaqs();
onMounted(fetchSuggestedQuestions);

const askSuggestion = async (suggestion: string) => {
  pendingMessage.value = suggestion;
  await navigateTo('/chat');
};

const onSubmit = async (e: Event) => {
  e.preventDefault();

  const trimmed = question.value.trim();
  if (!trimmed) return;

  pendingMessage.value = trimmed;
  question.value = '';
  await navigateTo('/chat');
};
</script>
