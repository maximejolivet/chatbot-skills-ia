<template>
  <main
    class="hero-wash relative flex min-h-screen items-center justify-center overflow-hidden px-6"
  >
    <div
      aria-hidden="true"
      class="hero-aura pointer-events-none absolute inset-0 animate-aura-drift motion-reduce:animate-none"
    />

    <div class="relative mx-auto flex max-w-2xl flex-col items-center text-center">
      <NuxtImg
        src="/maximejolivet.jpg"
        alt="Maxime"
        width="112"
        height="112"
        format="webp"
        class="mb-6 h-20 w-20 animate-breathe rounded-full object-cover shadow-lg shadow-foreground/10 motion-reduce:animate-none sm:h-28 sm:w-28"
      />

      <div class="mb-8 flex items-center gap-2">
        <span
          class="inline-flex items-center gap-2 rounded-full bg-muted px-4 py-1.5 text-[13px] font-medium tracking-wide text-accent"
        >
          <span class="h-1.5 w-1.5 rounded-full bg-accent" />
          {{ $t('home.badge') }}
        </span>
        <span
          class="rounded-full bg-accent/10 px-2 py-1 text-[10px] font-bold uppercase leading-none tracking-wide text-accent"
        >
          {{ $t('chatbot.betaBadge') }}
        </span>
      </div>

      <h1 class="font-serif text-[2.75rem] font-medium leading-[1.05] tracking-tight sm:text-6xl">
        {{ $t('home.heading') }}
      </h1>

      <p class="mt-6 max-w-md text-lg leading-relaxed text-muted-foreground">
        {{ $t('home.subheading') }}
      </p>

      <HeroChatBar class="mt-10 max-w-md" />
    </div>

    <StickyChatBubble />
  </main>
</template>
