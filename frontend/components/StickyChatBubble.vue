<template>
  <!-- Below sm: the hover-teaser bubble/popin above doesn't fit a touch
  screen well (no hover, and the popin would crowd an already-small
  viewport) -- a single tap button that goes straight to the dedicated
  /chat page instead. -->
  <NuxtLink
    to="/chat"
    :aria-label="$t('stickyBubble.start')"
    class="fixed bottom-6 right-6 z-50 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-xl shadow-foreground/20 transition-transform hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 sm:hidden"
  >
    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        stroke-width="2"
        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
      />
    </svg>
  </NuxtLink>

  <div class="fixed bottom-6 right-6 z-50 hidden flex-col items-end gap-3 sm:flex">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-4 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-4 scale-95"
    >
      <Chatbot
        v-if="isOpen"
        variant="widget"
        :title="$t('chatbot.defaultTitle')"
        api-url="/api"
        :placeholder="$t('chatbot.writePlaceholder')"
        show-close
        @close="isOpen = false"
      />
    </Transition>

    <div class="group flex items-center gap-3">
      <span
        v-if="!isOpen"
        :class="[
          'pointer-events-none translate-x-2 whitespace-nowrap rounded-full bg-card px-3.5 py-2 text-sm font-medium text-card-foreground opacity-0 shadow-lg shadow-foreground/10 transition-all duration-200 group-hover:translate-x-0 group-hover:opacity-100 group-focus-within:translate-x-0 group-focus-within:opacity-100',
          lastMessagePreview ? 'max-w-xs truncate' : '',
        ]"
      >
        {{ lastMessagePreview ?? $t('stickyBubble.start') }}
      </span>
      <button
        type="button"
        :aria-label="isOpen ? $t('stickyBubble.close') : $t('stickyBubble.start')"
        :aria-expanded="isOpen"
        class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-xl shadow-foreground/20 transition-transform hover:scale-105 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2"
        @click="onBubbleClick"
      >
        <span
          v-if="showFirstVisitBadge"
          aria-hidden="true"
          class="absolute -top-0.5 -right-0.5 flex h-3.5 w-3.5"
        >
          <span
            class="absolute inline-flex h-full w-full animate-pulse-ring rounded-full bg-accent motion-reduce:animate-none"
          />
          <span
            class="relative inline-flex h-3.5 w-3.5 rounded-full bg-accent ring-2 ring-background"
          />
        </span>
        <svg v-if="!isOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
          />
        </svg>
        <svg v-else class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M6 18L18 6M6 6l12 12"
          />
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
const isOpen = ref(false);

// Discreet ping badge drawing the eye to the bubble on a visitor's first
// visit -- gone for good (any browser tab, any later visit) the moment they
// interact with it, tracked separately from CONVERSATION_ID_STORAGE_KEY
// since "noticed the bubble" and "started a conversation" are different
// things (a visitor who opens then closes the popin without sending
// anything has still seen it).
const BUBBLE_SEEN_KEY = 'chatbot:bubble_seen';
const showFirstVisitBadge = ref(false);

// Teaser of the last assistant reply, read straight from localStorage
// (persisted by useChatbot.ts on every completed reply, from whichever
// mount actually had the conversation open) -- a signal that "there's
// already something here" for a returning visitor, before they've even
// clicked. null when no conversation has happened yet, falls back to the
// plain "start a conversation" tooltip below.
const lastMessagePreview = ref<string | null>(null);

onMounted(() => {
  showFirstVisitBadge.value = localStorage.getItem(BUBBLE_SEEN_KEY) !== '1';
  lastMessagePreview.value = localStorage.getItem(LAST_MESSAGE_PREVIEW_KEY);
});

// A conversation already exists (id persisted by useChatbot's
// ensureConversation) -- send the visitor to /chat to pick it back up there
// instead of restarting a second thread in the popin.
const onBubbleClick = () => {
  if (showFirstVisitBadge.value) {
    showFirstVisitBadge.value = false;
    localStorage.setItem(BUBBLE_SEEN_KEY, '1');
  }

  if (isOpen.value) {
    isOpen.value = false;
    return;
  }

  const hasStartedConversation = !!localStorage.getItem(CONVERSATION_ID_STORAGE_KEY);
  if (hasStartedConversation) {
    navigateTo('/chat');
    return;
  }

  isOpen.value = true;
};

// Cmd/Ctrl+K opens (or closes) the widget from anywhere on the page --
// distinct from Chatbot.vue's own "/" shortcut, which only refocuses the
// input once the panel is already open.
const onKeydown = (e: KeyboardEvent) => {
  if ('k' !== e.key || !(e.metaKey || e.ctrlKey)) return;
  e.preventDefault();
  onBubbleClick();
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>
