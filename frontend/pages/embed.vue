<template>
  <StickyChatBubble embedded :headless="headless" />
</template>

<script setup lang="ts">
// Nothing else on this page on purpose: public/widget.js iframes this route
// into a third-party host page, so anything beyond the bubble/panel itself
// (the "/chat" hero, index.vue's own markup, app.vue's chrome) would just
// be dead space the host page's background shows through around.
useHead({
  title: 'chatbot-ia-widget',
  meta: [{ name: 'robots', content: 'noindex' }],
  // A browser paints an ordinary HTML document's canvas white by default --
  // "no bg-* class anywhere" (true of html/body/#__nuxt here) does NOT mean
  // transparent, it means opaque white, which showed up as a plain white
  // rectangle behind widget.js's fixed-size iframe wherever the bubble/panel
  // (bottom-anchored inside it) doesn't itself reach. Injected here instead
  // of assets/css/main.css so every other route keeps its normal (opaque)
  // canvas -- this only applies while /embed is the active page.
  style: [{ innerHTML: 'html, body, #__nuxt { background: transparent; }' }],
});

// Set by public/widget.js (?headless=1) when the host page passed its own
// data-trigger selector -- that host button is the only way to open the
// widget then, so StickyChatBubble's own round button/mobile tab would just
// be a second, redundant toggle floating on top of the host's UI.
const headless = '1' === useRoute().query.headless;
</script>
