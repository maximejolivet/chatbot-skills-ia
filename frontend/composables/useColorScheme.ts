import type { MaybeRefOrGetter } from 'vue';

export type ColorScheme = 'light' | 'dark';

const STORAGE_KEY = 'chatbot:color_scheme';

// Same-tab sync between every mounted instance of this composable (app.vue's
// own root *and* every mounted Chatbot.vue instance). The native `storage`
// event only fires in *other* tabs, never the one that made the write, so
// without this a toggle() in one instance (e.g. Chatbot.vue's header button)
// wouldn't reach any other instance already mounted in the same page (e.g.
// app.vue's own root, which pages/index.vue's `.hero-wash` background
// depends on) until that other instance's next mount -- visible as a
// background stuck on the old theme after toggling from inside the widget.
const CHANGE_EVENT = 'chatbot:color_scheme_change';

const readStored = (): ColorScheme | null => {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    return 'light' === stored || 'dark' === stored ? stored : null;
  } catch {
    // localStorage unavailable (private mode, disabled) -- fall through to
    // system-preference/fallback resolution below, same as "never chosen".
    return null;
  }
};

const systemPrefersDark = () =>
  'undefined' !== typeof window && window.matchMedia('(prefers-color-scheme: dark)').matches;

/**
 * Resolves to, in priority order: the visitor's own explicit choice
 * (persisted in localStorage, wins over everything including a page
 * re-embedding the widget with a different `fallback`), `hostScheme` (the
 * host page's own dark/light state, forwarded when this is StickyChatBubble
 * embedded via public/widget.js -- a visitor landing on a site already in
 * dark mode should get a dark widget, not one following their unrelated OS
 * setting), the OS/browser `prefers-color-scheme`, then `fallback` (the
 * `theme` prop callers can still pass to Chatbot.vue). Client-only by
 * nature (matchMedia/localStorage) -- `scheme` starts at `fallback` for SSR
 * and corrects itself in onMounted, same tradeoff as
 * useOnlineStatus/useDebugMode: a possible one-frame flash of the wrong
 * theme rather than a blocking inline script to avoid it.
 */
export const useColorScheme = (
  fallback: ColorScheme = 'light',
  hostScheme?: MaybeRefOrGetter<ColorScheme | null | undefined>,
) => {
  const scheme = ref<ColorScheme>(fallback);

  onMounted(() => {
    const stored = readStored();
    scheme.value = stored ?? toValue(hostScheme) ?? (systemPrefersDark() ? 'dark' : fallback);

    // Only track live OS-level/host changes while the visitor hasn't made
    // an explicit choice of their own -- once they have, it's a deliberate
    // override, not something either should silently walk back.
    if (!stored) {
      const media = window.matchMedia('(prefers-color-scheme: dark)');
      const onChange = (e: MediaQueryListEvent) => {
        if (!readStored() && !toValue(hostScheme)) scheme.value = e.matches ? 'dark' : 'light';
      };
      media.addEventListener('change', onChange);
      onBeforeUnmount(() => media.removeEventListener('change', onChange));

      // Live sync when the host page's own theme changes after mount (its
      // own dark-mode toggle, mid-session) -- hostScheme is only ever a
      // ref/getter when the caller is prepared to update it (StickyChatBubble
      // watching public/widget.js's postMessage), a plain value never
      // changes so this watch is simply inert then.
      if (hostScheme) {
        watch(
          () => toValue(hostScheme),
          (value) => {
            if (!readStored() && value) scheme.value = value;
          },
        );
      }
    }

    // Unconditional (unlike the OS-preference listener above): this mirrors
    // the visitor's *own* explicit toggle from another instance, which must
    // always win regardless of how this instance's current value was
    // resolved.
    const onExternalChange = (e: Event) => {
      scheme.value = (e as CustomEvent<ColorScheme>).detail;
    };
    window.addEventListener(CHANGE_EVENT, onExternalChange);
    onBeforeUnmount(() => window.removeEventListener(CHANGE_EVENT, onExternalChange));
  });

  const toggle = () => {
    scheme.value = 'dark' === scheme.value ? 'light' : 'dark';
    try {
      localStorage.setItem(STORAGE_KEY, scheme.value);
    } catch {
      // Toggle still works for this session, just doesn't persist.
    }
    window.dispatchEvent(new CustomEvent(CHANGE_EVENT, { detail: scheme.value }));
  };

  return { scheme, toggle };
};
