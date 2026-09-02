<template>
  <main class="hero-wash relative flex h-full flex-col overflow-hidden">
    <div
      aria-hidden="true"
      class="hero-aura pointer-events-none absolute inset-0 animate-aura-drift motion-reduce:animate-none"
    />

    <Chatbot
      class="relative"
      variant="page"
      :title="$t('chatbot.defaultTitle')"
      api-url="/api"
      :placeholder="$t('chatbot.writePlaceholder')"
    />
  </main>
</template>
