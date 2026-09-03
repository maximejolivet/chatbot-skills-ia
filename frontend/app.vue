<template>
  <div :class="[themeClass, 'h-full font-sans text-foreground antialiased']">
    <NuxtPage />
  </div>
</template>

<script setup lang="ts">
const { t } = useI18n();

// Resolves the same stored/system preference Chatbot.vue's own header
// toggle uses (composables/useColorScheme.ts), applied here -- alongside
// `text-foreground` on the same element -- so every page's own markup (not
// just Chatbot.vue's self-contained subtree, which resolves its own scheme
// independently since it's also embeddable standalone) reacts to dark mode.
// `text-foreground`'s resolved color otherwise inherits down as a fixed
// value from wherever it's declared, so a descendant page toggling `.dark`
// on its own root doesn't repaint text colored by an ancestor -- it has to
// sit here, at the same level as `text-foreground` itself.
//
// hostScheme (useHostScheme, shared with Chatbot.vue's own instance) so this
// root doesn't independently resolve OS-preference-only when embedded --
// see the long comment on useHostScheme for why a plain, unrelated .dark
// here would otherwise leak into the panel's own CSS custom properties.
const { scheme } = useColorScheme(undefined, useHostScheme());
const themeClass = computed(() => (scheme.value === 'dark' ? 'dark' : ''));

useHead({
  title: t('meta.title'),
  meta: [
    { name: 'description', content: t('meta.description') },
    // pages/embed.vue paints html/body/#__nuxt fully transparent so the
    // iframe lets the host page show through around StickyChatBubble (its
    // open panel is a fixed guess size -- see public/widget.js's
    // FALLBACK_OPEN_SIZE comment -- so there's always some transparent
    // margin left over around the real, smaller panel). But an element
    // whose *computed* background is transparent still needs the browser to
    // paint something behind it, and without an explicit color-scheme the
    // UA default for that fallback paint is white regardless of our own
    // dark class -- invisible on embed's own light theme (close enough to
    // white already) but a stray white margin around the widget on any
    // dark-mode host page. Reactive to `scheme`, not just the initial
    // ?theme= query value baked into pages/embed.vue's hostTheme, so it
    // still flips correctly if the host toggles its own theme mid-session.
    { name: 'color-scheme', content: computed(() => scheme.value) },
  ],
});
</script>
