/**
 * Embeddable chatbot widget loader.
 *
 * Drop this on any third-party page:
 *   <script src="https://<this-app-origin>/widget.js" async></script>
 *
 * It injects an <iframe src="<origin>/embed"> (pages/embed.vue, which mounts
 * only <StickyChatBubble embedded />) fixed in the bottom-right corner, and
 * resizes that iframe between a small "closed bubble" box and a larger
 * "open panel" box by listening for the postMessage StickyChatBubble.vue's
 * notifyEmbedHost() sends on every open/close. The sizes below are a fixed
 * approximation of that component's own Tailwind box (button + bottom-6/
 * right-6 margins for closed; the widget panel + button row + gaps for
 * open) -- they don't need to be pixel-perfect, just big enough that
 * nothing gets clipped and small enough that the transparent iframe box
 * doesn't cover host-page content the visitor still needs to click.
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

  var CLOSED_SIZE = { width: '340px', height: '104px' };
  var OPEN_SIZE = { width: '480px', height: '620px' };

  function applySize(iframe, size) {
    iframe.style.width = size.width;
    iframe.style.height = size.height;
  }

  function inject() {
    var iframe = document.createElement('iframe');
    iframe.id = IFRAME_ID;
    iframe.src = ORIGIN + '/embed';
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

    document.body.appendChild(iframe);
  }

  if ('loading' === document.readyState) {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
