<template>
  <StickyChatBubble embedded />
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
</script>
