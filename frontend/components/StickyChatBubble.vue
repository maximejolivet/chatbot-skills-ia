<template>
  <!-- Below sm: the hover-teaser bubble/popin above doesn't fit a touch
  screen well (no hover, and the popin would crowd an already-small
  viewport) -- a single tap button that goes straight to the dedicated
  /chat page instead. Skipped entirely when embedded (pages/embed.vue):
  the sm: breakpoint would resolve against the iframe's own tiny width,
  not the host page's, so it'd render as "mobile" even on a desktop host
  -- and navigating to /chat inside the iframe would strand the visitor
  in a small broken view instead of the host page. -->
  <NuxtLink
    v-if="!embedded"
    to="/chat"
    :aria-label="$t('stickyBubble.start')"
    class="fixed bottom-4 right-0 z-50 flex size-11 items-center justify-center rounded-l-full border-y border-l-0 border-r border-primary bg-primary text-primary-foreground shadow-lg transition-transform duration-300 hover:scale-105 hover:bg-accent hover:border-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 sm:hidden"
  >
    <svg
      class="pointer-events-none size-5 translate-x-px"
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
  </NuxtLink>

  <div
    ref="wrapperRef"
    :class="[
      'fixed bottom-4 right-4 z-50 flex-col items-end gap-3 sm:right-6',
      embedded ? 'flex' : 'hidden sm:flex',
    ]"
  >
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
        :host-scheme="hostScheme"
        show-close
        @close="
          isOpen = false;
          notifyEmbedHost();
        "
      />
    </Transition>

    <div v-if="!headless" class="group flex items-center gap-3">
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
// Set only by pages/embed.vue, which mounts this component alone inside
// the iframe that frontend/public/widget.js injects into a third-party
// host page (see nuxt.config.ts's routeRules['/embed'] for the matching
// CSP relaxation). Everywhere else (pages/index.vue) this runs at the
// site's own top level, so all the branches below stay their normal,
// pre-embed behaviour.
// headless (set via pages/embed.vue's ?headless=1, itself set by widget.js
// when the host page passed its own data-trigger selector) drops the round
// button/mobile tab entirely -- the host's own button is the only way to
// open the widget then, via the postMessage listener below. Only
// meaningful alongside embedded: true, since it's public/widget.js on the
// host page that owns and clicks that external trigger.
// hostTheme: the host page's dark/light state at the moment public/widget.js
// injected the iframe (its src carries ?theme=dark|light), so the widget
// doesn't default to the visitor's unrelated OS preference while the host
// site is already, say, in dark mode. Kept in a ref (not just read once)
// because the host can still toggle its own theme after the iframe loads --
// see onHostMessage below, which widget.js's MutationObserver drives.
const props = withDefaults(
  defineProps<{ embedded?: boolean; headless?: boolean; hostTheme?: 'light' | 'dark' | null }>(),
  {
    embedded: false,
    headless: false,
    hostTheme: null,
  },
);

const hostScheme = ref(props.hostTheme);

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
// instead of restarting a second thread in the popin. Not when embedded:
// /chat would load inside the small iframe itself rather than the host
// page, so an existing conversation just reopens in the popin there too.
const onBubbleClick = () => {
  if (showFirstVisitBadge.value) {
    showFirstVisitBadge.value = false;
    localStorage.setItem(BUBBLE_SEEN_KEY, '1');
  }

  if (isOpen.value) {
    isOpen.value = false;
    notifyEmbedHost();
    return;
  }

  const hasStartedConversation = !!localStorage.getItem(CONVERSATION_ID_STORAGE_KEY);
  if (hasStartedConversation && !props.embedded) {
    navigateTo('/chat');
    return;
  }

  isOpen.value = true;
  notifyEmbedHost();
};

// Tells frontend/public/widget.js (running on the host page) to resize the
// iframe between its small closed-bubble box and the full open-panel size
// -- the iframe's own viewport has no idea how big the host page actually
// is, so it can't size itself. No-op outside pages/embed.vue.
const notifyEmbedHost = () => {
  if (!props.embedded) return;
  window.parent.postMessage(
    { source: 'chatbot-ia-widget', type: 'toggle', open: isOpen.value },
    '*',
  );
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

// The headless open command from public/widget.js, sent when a visitor
// clicks the host page's own trigger element -- no origin check since this
// widget is meant to be embeddable on arbitrary third-party origins (same
// posture as notifyEmbedHost's outbound '*' below), and the message can
// only ever open the panel, never read or change anything sensitive.
// notifyEmbedHost() still fires from here so widget.js resizes the iframe
// through the same single 'toggle' listener it already uses everywhere
// else, instead of a second, parallel resize path just for this trigger.
const onHostMessage = (e: MessageEvent) => {
  if (!props.embedded) return;
  const data = e.data as { source?: string; type?: string; theme?: 'light' | 'dark' } | null;
  if (!data || 'chatbot-ia-widget' !== data.source) return;

  if ('theme' === data.type && data.theme) {
    hostScheme.value = data.theme;
    return;
  }

  if ('open' !== data.type) return;
  isOpen.value = true;
  notifyEmbedHost();
};

onMounted(() => window.addEventListener('message', onHostMessage));
onBeforeUnmount(() => window.removeEventListener('message', onHostMessage));

// Reports this component's actual rendered CLOSED box size to
// public/widget.js so it can size the iframe to match reality instead of
// guessing fixed pixel dimensions -- a guess drifts from the truth in
// easy-to-miss ways (headless mode has no button row, so its closed size is
// smaller than default mode's), and every drift shows up as a stretch of
// plain iframe canvas the bubble itself doesn't reach. Closed only, not
// open: the open panel's own width/height CSS (Chatbot.vue) is itself
// capped against this iframe's viewport, so reporting its rendered size
// back to widget.js -- which sets that very viewport from the report --
// would be circular (widget.js ignores it regardless; see its 'size'
// handler).
const wrapperRef = ref<HTMLElement | null>(null);
let resizeObserver: ResizeObserver | null = null;

onMounted(() => {
  if (!props.embedded || !wrapperRef.value) return;
  const report = () => {
    if (!wrapperRef.value || isOpen.value) return;
    const rect = wrapperRef.value.getBoundingClientRect();
    window.parent.postMessage(
      {
        source: 'chatbot-ia-widget',
        type: 'size',
        open: false,
        width: Math.ceil(rect.width),
        height: Math.ceil(rect.height),
      },
      '*',
    );
  };
  resizeObserver = new ResizeObserver(report);
  resizeObserver.observe(wrapperRef.value);
});

onBeforeUnmount(() => resizeObserver?.disconnect());
</script>
