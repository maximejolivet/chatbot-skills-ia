<template>
  <div :class="[themeClass, 'font-sans text-foreground antialiased']">
    <NuxtPage />
  </div>
</template>

<script setup lang="ts">
const { t } = useI18n();

useHead({
  title: t('meta.title'),
  meta: [{ name: 'description', content: t('meta.description') }],
});

// Resolves the same stored/system preference Chatbot.vue's own header
// toggle uses (composables/useColorScheme.ts), applied here -- alongside
// `text-foreground` on the same element -- so every page's own markup (not
// just Chatbot.vue's self-contained subtree, which resolves its own scheme
// independently since it's also embeddable standalone) reacts to dark mode.
// `text-foreground`'s resolved color otherwise inherits down as a fixed
// value from wherever it's declared, so a descendant page toggling `.dark`
// on its own root doesn't repaint text colored by an ancestor -- it has to
// sit here, at the same level as `text-foreground` itself.
const { scheme } = useColorScheme();
const themeClass = computed(() => (scheme.value === 'dark' ? 'dark' : ''));
</script>
