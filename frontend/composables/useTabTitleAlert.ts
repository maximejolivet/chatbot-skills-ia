import { onBeforeUnmount, onMounted } from 'vue';

// Flashes the browser tab's title while the visitor has switched away
// mid-conversation, alternating with the page's own title -- a fallback
// unread signal for browsers/OSes where the Notification API (see
// useChatbot's notifyIfHidden) is blocked, denied, or simply ignored
// because the visitor never granted permission. Restores the original
// title itself the moment the tab regains visibility, so it never lingers
// stale on an unrelated later page.
const FLASH_INTERVAL_MS = 1500;

export const useTabTitleAlert = () => {
  let originalTitle: string | null = null;
  let flashInterval: ReturnType<typeof setInterval> | undefined;
  let flashOn = false;

  // pages/embed.vue -- public/widget.js's real production mount point -- is
  // always an iframe on a third-party host page. This document's own title
  // is never what the visitor's tab actually shows; only the HOST page's
  // is. Relayed there instead of touched locally -- see widget.js's
  // 'title-flash' listener, which owns the actual flashing (and its own,
  // more trustworthy document.hidden) for that case from here on.
  const inIframe = 'undefined' !== typeof window && window.self !== window.top;

  const stop = () => {
    if (flashInterval) {
      clearInterval(flashInterval);
      flashInterval = undefined;
    }
    if (null !== originalTitle) {
      document.title = originalTitle;
      originalTitle = null;
    }
    flashOn = false;
  };

  const onVisibilityChange = () => {
    if (!document.hidden) stop();
  };

  const flash = (alertText: string) => {
    if ('undefined' === typeof document) return;

    if (inIframe) {
      window.parent.postMessage(
        { source: 'chatbot-ia-widget', type: 'title-flash', text: alertText },
        '*',
      );
      return;
    }

    if (!document.hidden) return;

    originalTitle ??= document.title;
    if (flashInterval) clearInterval(flashInterval);

    flashOn = false;
    flashInterval = setInterval(() => {
      flashOn = !flashOn;
      document.title = flashOn ? alertText : (originalTitle ?? document.title);
    }, FLASH_INTERVAL_MS);
  };

  onMounted(() => document.addEventListener('visibilitychange', onVisibilityChange));
  onBeforeUnmount(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange);
    stop();
  });

  return { flash };
};
