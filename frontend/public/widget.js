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
 * bottom-right corner, and resizes that iframe by listening for the
 * postMessage StickyChatBubble.vue's notifyEmbedHost() sends on every open/
 * close. The sizes below are a fixed approximation of that component's own
 * Tailwind box (button + bottom-6/right-6 margins for closed; the widget
 * panel + button row + gaps for open) -- they don't need to be pixel-
 * perfect, just big enough that nothing gets clipped and small enough that
 * the transparent iframe box doesn't cover host-page content the visitor
 * still needs to click. In triggered mode the closed box is 0x0 instead --
 * StickyChatBubble's own bubble/tab is hidden there (?headless=1), so
 * there's nothing of its own to show or click until the host's button
 * opens it.
 *
 * Also passes the host page's own dark/light state (?theme=dark|light,
 * read off <html class="dark">, the standard Tailwind darkMode:'class'
 * convention -- this app's own pages/embed.vue and this host page both use
 * it) so the widget matches a host page already in dark mode instead of
 * defaulting to the visitor's unrelated OS preference, and keeps it synced
 * afterwards if the host's own toggle changes it mid-session.
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
  // CSS min(), not a JS/resize-listener computation: 100vw/100vh here are
  // the HOST page's viewport (this script runs there, not inside the
  // iframe), and the browser keeps these correct across resize/rotation on
  // its own. Without the cap, a narrow phone viewport (< 480px) would still
  // get a 480px-wide iframe fixed to the right edge -- clipping its left
  // side, since a fixed-position element doesn't shrink to fit like normal
  // in-flow content would.
  var CLOSED_SIZE = TRIGGER_SELECTOR
    ? { width: '0px', height: '0px' }
    : { width: 'min(340px, 100vw)', height: '104px' };
  var OPEN_SIZE = { width: 'min(480px, 100vw)', height: 'min(620px, 100vh)' };

  function applySize(iframe, size) {
    iframe.style.width = size.width;
    iframe.style.height = size.height;
  }

  function hostTheme() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
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
    applySize(iframe, CLOSED_SIZE);

    window.addEventListener('message', function (event) {
      if (event.origin !== ORIGIN) return;
      var data = event.data;
      if (!data || 'chatbot-ia-widget' !== data.source || 'toggle' !== data.type) return;
      applySize(iframe, data.open ? OPEN_SIZE : CLOSED_SIZE);
    });

    // Live sync if the host toggles its own dark mode after the iframe
    // already loaded -- most such toggles just flip the class on <html>
    // rather than replacing it outright, so 'class' is the one attribute
    // worth observing here.
    var lastTheme = hostTheme();
    new MutationObserver(function () {
      var theme = hostTheme();
      if (theme === lastTheme) return;
      lastTheme = theme;
      iframe.contentWindow.postMessage(
        { source: 'chatbot-ia-widget', type: 'theme', theme: theme },
        ORIGIN,
      );
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

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
