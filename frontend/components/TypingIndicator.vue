<template>
  <div class="mb-3 flex items-end justify-start gap-2">
    <NuxtImg
      src="/maximejolivet.jpg"
      alt="Maxime"
      width="36"
      height="36"
      format="webp"
      class="mb-1 h-7 w-7 shrink-0 rounded-full object-cover sm:h-9 sm:w-9"
    />
    <div
      class="flex items-center gap-2 rounded-2xl rounded-bl-md border border-border bg-card px-4 py-3"
    >
      <span v-if="label" class="font-sans text-xs text-muted-foreground">{{ label }}</span>
      <span v-else class="sr-only">{{ $t('chatbot.assistantTyping') }}</span>
      <span class="flex items-center gap-1" aria-hidden="true">
        <span
          class="h-1.5 w-1.5 animate-bounce-slow rounded-full bg-muted-foreground motion-reduce:animate-none"
        />
        <span
          class="h-1.5 w-1.5 animate-bounce-slow rounded-full bg-muted-foreground motion-reduce:animate-none"
          style="animation-delay: 0.1s"
        />
        <span
          class="h-1.5 w-1.5 animate-bounce-slow rounded-full bg-muted-foreground motion-reduce:animate-none"
          style="animation-delay: 0.2s"
        />
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
// Indicateur de frappe façon Messenger -- le label optionnel remplace le
// silence par une phrase de progression pendant le chemin bufferisé du
// tool-calling (voir useChatbot.ts::toolCallLabel), qui n'émet aucun delta
// tant qu'un outil est en cours d'exécution.
defineProps<{ label?: string | null }>();
</script>
