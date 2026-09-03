/**
 * Embeddable chatbot widget loader.
 *
 * Default mode -- a self-contained floating bubble:
 *   <script src="https://<this-app-origin>/widget.js" async></script>
 *
 * Triggered mode -- no bubble of its own; opens when the host page's own
 * button (already styled/positioned however the host wants) is clicked:
 *   <script src="https://<this-app-origin>/widget.js" data-trigger="#book-btn" async></script>
 * `data-trigger` is any CSS selector, matched against the whole page --
 * every matching element opens the widget on click (querySelectorAll, not
 * just the first match).
 *
 * Either way this injects an <iframe src="<origin>/embed"> (pages/embed.vue,
 * which mounts only <StickyChatBubble embedded [headless] />) fixed in the
 * bottom-right corner. The closed box is sized to match StickyChatBubble's
 * own real rendered box, reported back via a 'size' postMessage (a
 * ResizeObserver there) -- not a fixed guess: a guess drifts from reality in
 * easy-to-miss ways (headless mode has no button row, so its closed size is
 * smaller than default mode's), and every bit of drift shows up as a
 * stretch of plain iframe canvas past wherever the bubble itself actually
 * reaches. The open panel stays on the static FALLBACK_OPEN_SIZE formula
 * below instead -- see the 'size' handler for why measuring it the same way
 * is circular. FALLBACK_CLOSED_SIZE is what's applied before that first
 * real measurement arrives. In triggered mode the closed box is 0x0 --
 * StickyChatBubble's own bubble/tab is hidden there (?headless=1), so
 * there's nothing of its own to show or click until the host's button
 * opens it.
 *
 * Also passes the host page's own dark/light state (?theme=dark|light) so
 * the widget matches a host page already in dark mode instead of
 * defaulting to the visitor's unrelated OS preference, and keeps it synced
 * afterwards if the host's own toggle changes it mid-session. Detected off
 * whichever convention the host actually uses -- there's no single
 * standard, so hostTheme() below checks each one it can, in order:
 * <html class="dark"> (Tailwind's own darkMode:'class', used by this app's
 * pages/embed.vue itself), <html data-theme="dark|night"> (a common
 * alternative -- e.g. maxime.bzh, a separate site by the same author,
 * uses data-theme="night"/"day"), then falls back to the visitor's OS
 * preference if neither attribute is set at all.
 */
(function () {
  'use strict';

  var CURRENT_SCRIPT = document.currentScript;
  if (!CURRENT_SCRIPT || !CURRENT_SCRIPT.src) return;

  var ORIGIN = new URL(CURRENT_SCRIPT.src).origin;
  var IFRAME_ID = 'chatbot-ia-widget-frame';

  // Re-running this script on the same page (host re-injects it, SPA route
  // change re-executes it, ...) must not stack a second iframe.
  if (document.getElementById(IFRAME_ID)) return;

  var TRIGGER_SELECTOR = CURRENT_SCRIPT.dataset.trigger || '';
  var ZERO_SIZE = { width: '0px', height: '0px' };
  // Only used before StickyChatBubble's first real 'size' report -- a
  // rough approximation of its Tailwind box (button + margins for closed;
  // panel + button row + gaps for open), just to avoid an empty flash.
  var FALLBACK_CLOSED_SIZE = TRIGGER_SELECTOR
    ? ZERO_SIZE
    : { width: 'min(340px, 100vw)', height: '104px' };
  var FALLBACK_OPEN_SIZE = { width: 'min(480px, 100vw)', height: 'min(620px, 100vh)' };
  var measuredClosedSize = FALLBACK_CLOSED_SIZE;
  var measuredOpenSize = FALLBACK_OPEN_SIZE;
  var isOpen = false;

  // Flashes THIS (host) page's own tab title on a 'title-flash' message from
  // the iframe -- composables/useTabTitleAlert.ts can't touch it directly,
  // since document.title inside the iframe is invisible to the visitor.
  // document.hidden here is the host's own, real visibility state (more
  // trustworthy than relaying the iframe's), so it alone decides whether to
  // actually start flashing; the host's own visibilitychange listener below
  // is likewise the sole authority on when to stop, decoupled from whatever
  // the iframe does on its side.
  var TITLE_FLASH_INTERVAL_MS = 1500;
  var originalHostTitle = null;
  var titleFlashInterval = null;
  var titleFlashOn = false;

  function stopTitleFlash() {
    if (titleFlashInterval) {
      clearInterval(titleFlashInterval);
      titleFlashInterval = null;
    }
    if (null !== originalHostTitle) {
      document.title = originalHostTitle;
      originalHostTitle = null;
    }
    titleFlashOn = false;
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) stopTitleFlash();
  });

  function applySize(iframe, size) {
    iframe.style.width = size.width;
    iframe.style.height = size.height;
  }

  // CSS min(), not a JS/resize-listener computation: 100vw/100vh here are
  // the HOST page's viewport (this script runs here, not inside the
  // iframe), and the browser keeps these correct across resize/rotation on
  // its own. Without the cap, a phone viewport narrower than the reported
  // width would still get an iframe fixed to the right edge at that width
  // -- clipping its left side, since a fixed-position element doesn't
  // shrink to fit like normal in-flow content would.
  function toCssSize(width, height) {
    return { width: 'min(' + width + 'px, 100vw)', height: 'min(' + height + 'px, 100vh)' };
  }

  function hostTheme() {
    var html = document.documentElement;
    if (html.classList.contains('dark')) return 'dark';
    if (html.classList.contains('light')) return 'light';
    var dataTheme = html.dataset.theme;
    if (dataTheme) {
      return 'dark' === dataTheme || 'night' === dataTheme ? 'dark' : 'light';
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  function inject() {
    var params = TRIGGER_SELECTOR ? ['headless=1'] : [];
    params.push('theme=' + hostTheme());

    var iframe = document.createElement('iframe');
    iframe.id = IFRAME_ID;
    iframe.src = ORIGIN + '/embed?' + params.join('&');
    iframe.title = 'Assistant IA';
    iframe.setAttribute('scrolling', 'no');
    iframe.setAttribute('allowtransparency', 'true');
    iframe.style.position = 'fixed';
    iframe.style.bottom = '0';
    iframe.style.right = '0';
    iframe.style.border = 'none';
    iframe.style.background = 'transparent';
    // Above virtually anything a host page sets, short of it also reaching
    // into the high end of the range itself.
    iframe.style.zIndex = '2147483000';
    applySize(iframe, measuredClosedSize);

    window.addEventListener('message', function (event) {
      if (event.origin !== ORIGIN) return;
      var data = event.data;
      if (!data || 'chatbot-ia-widget' !== data.source) return;

      if ('toggle' === data.type) {
        isOpen = !!data.open;
        applySize(iframe, isOpen ? measuredOpenSize : measuredClosedSize);
        return;
      }

      if ('size' === data.type) {
        // Open-state reports are intentionally ignored, both dimensions --
        // measuredOpenSize stays on FALLBACK_OPEN_SIZE's static formula.
        // Chatbot.vue caps the open panel at
        // h-[min(30rem,calc(100vh-6rem))] w-[min(28rem,calc(100vw-2rem))],
        // and that vw/vh is THIS iframe's own viewport -- the very thing
        // this script would be setting from the measurement. Applying a
        // measured open size feeds back into that formula: apply it, the
        // panel's vw/vh shrinks, its measured size shrinks with it, apply
        // that, and so on -- the panel collapses to nothing a couple of
        // round trips in. The closed box has no such self-reference
        // (button + tooltip, fixed pixel sizes only), so it alone is safe
        // to take from the live measurement.
        if (data.open) return;
        measuredClosedSize = toCssSize(data.width, data.height);
        // Live-update immediately only if the iframe is currently actually
        // showing the closed state.
        if (!isOpen) applySize(iframe, measuredClosedSize);
        return;
      }

      if ('title-flash' === data.type) {
        if (!document.hidden) return;
        if (null === originalHostTitle) originalHostTitle = document.title;
        if (titleFlashInterval) clearInterval(titleFlashInterval);
        titleFlashOn = false;
        titleFlashInterval = setInterval(function () {
          titleFlashOn = !titleFlashOn;
          document.title = titleFlashOn ? data.text : originalHostTitle || document.title;
        }, TITLE_FLASH_INTERVAL_MS);
        return;
      }

      // Desktop notification for a reply that arrived while the visitor had
      // switched away -- relayed here because Notification.requestPermission()
      // is flat-out disallowed inside the iframe itself (a cross-origin-iframe
      // restriction with no `allow` attribute workaround, unlike camera/mic --
      // see composables/useChatbot.ts's notifyIfHidden comment). Runs from
      // this real top-level document instead, where it's unrestricted.
      if ('notify-permission' === data.type) {
        if ('undefined' === typeof Notification || 'default' !== Notification.permission) return;
        Notification.requestPermission();
        return;
      }

      if ('notify' === data.type) {
        if ('undefined' === typeof Notification || 'granted' !== Notification.permission) return;
        var notification = new Notification(data.title, { body: data.body });
        notification.onclick = function () {
          window.focus();
          notification.close();
        };
      }
    });

    // Live sync if the host toggles its own dark mode after the iframe
    // already loaded -- covers both conventions hostTheme() reads (a class
    // toggle, or a data-theme attribute toggle).
    var lastTheme = hostTheme();
    new MutationObserver(function () {
      var theme = hostTheme();
      if (theme === lastTheme) return;
      lastTheme = theme;
      iframe.contentWindow.postMessage(
        { source: 'chatbot-ia-widget', type: 'theme', theme: theme },
        ORIGIN,
      );
    }).observe(document.documentElement, {
      attributes: true,
      attributeFilter: ['class', 'data-theme'],
    });

    document.body.appendChild(iframe);

    if (!TRIGGER_SELECTOR) return;
    // Sizing itself happens above, through the same 'toggle' message
    // StickyChatBubble already sends on every open/close -- this just tells
    // it to open, once the visitor clicks the host's own button.
    document.querySelectorAll(TRIGGER_SELECTOR).forEach(function (el) {
      el.addEventListener('click', function () {
        iframe.contentWindow.postMessage({ source: 'chatbot-ia-widget', type: 'open' }, ORIGIN);
      });
    });
  }

  if ('loading' === document.readyState) {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
