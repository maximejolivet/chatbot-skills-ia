<template>
  <StickyChatBubble embedded :headless="headless" />
</template>

<script setup lang="ts">
// Nothing else on this page on purpose: public/widget.js iframes this route
// into a third-party host page, so anything beyond the bubble/panel itself
// (the "/chat" hero, index.vue's own markup, app.vue's chrome) would just
// be dead space the host page's background shows through around -- html/
// body/#__nuxt have no bg-* class anywhere in this app (assets/css/main.css
// only sets height:100%), so the iframe stays transparent without any
// extra styling here.
useHead({
  title: 'chatbot-ia-widget',
  meta: [{ name: 'robots', content: 'noindex' }],
});

// Set by public/widget.js (?headless=1) when the host page passed its own
// data-trigger selector -- that host button is the only way to open the
// widget then, so StickyChatBubble's own round button/mobile tab would just
// be a second, redundant toggle floating on top of the host's UI.
const headless = '1' === useRoute().query.headless;
</script>
