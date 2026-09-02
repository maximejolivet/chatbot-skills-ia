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
 * bottom-right corner. Sized to match StickyChatBubble's own real rendered
 * box, reported back via a 'size' postMessage (a ResizeObserver there,
 * covering both open and closed) -- not a fixed guess: a guess drifts from
 * reality in easy-to-miss ways (headless mode has no button row, so its
 * closed size is smaller than default mode's closed size; open differs by
 * conversation/theme too), and every bit of drift shows up as a stretch of
 * plain iframe canvas past wherever the panel itself actually reaches.
 * FALLBACK_CLOSED_SIZE/FALLBACK_OPEN_SIZE below are only what's applied
 * before that first real measurement arrives. In triggered mode the
 * closed box is 0x0 --
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
        var size = toCssSize(data.width, data.height);
        if (data.open) {
          measuredOpenSize = size;
        } else {
          measuredClosedSize = size;
        }
        // Live-update immediately only if this size report is for whichever
        // state (open/closed) the iframe is currently actually showing.
        if (data.open === isOpen) applySize(iframe, size);
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
