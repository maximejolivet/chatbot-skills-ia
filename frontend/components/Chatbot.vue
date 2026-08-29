<template>
  <div
    :class="[
      themeClass,
      className,
      variant === 'page' ? 'flex w-full flex-1 flex-col min-h-0' : '',
    ]"
  >
    <div
      :class="[
        'relative flex flex-col overflow-hidden transition-all duration-200',
        variant === 'page'
          ? [
              'h-full min-h-0',
              // Light mode: the ambient hero-wash/hero-aura gradient behind
              // this page (pages/chat.vue) is meant to bleed through here,
              // so no opaque background. Dark mode has no dark equivalent of
              // that gradient -- an opaque bg-background keeps the bubbles
              // legible instead of dark text over a bright pastel wash.
              scheme === 'dark' ? 'bg-background' : '',
            ]
          : [
              'rounded-3xl border border-border bg-background shadow-2xl shadow-foreground/10',
              'h-[min(30rem,calc(100vh-6rem))] w-[min(28rem,calc(100vw-2rem))]',
            ],
      ]"
    >
      <!-- Barre de progression indéterminée pendant la génération -- style
           GitHub/YouTube, posée sur le bord supérieur du panneau (les deux
           variantes ont overflow-hidden ici, donc elle ne déborde jamais). -->
      <div
        v-if="isLoading"
        class="absolute inset-x-0 top-0 z-20 h-0.5 overflow-hidden bg-transparent"
        aria-hidden="true"
      >
        <div class="h-full w-1/3 animate-loading-bar rounded-full bg-accent" />
      </div>

      <!-- En-tête (widget flottant uniquement) -->
      <div v-if="variant !== 'page'" class="border-b border-border bg-card px-4 py-3 sm:px-6">
        <div class="mx-auto flex w-full max-w-3xl items-center justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3">
            <div class="relative shrink-0">
              <NuxtImg
                src="/maximejolivet.jpg"
                alt="Maxime"
                width="48"
                height="48"
                format="webp"
                class="h-10 w-10 animate-breathe rounded-full object-cover motion-reduce:animate-none sm:h-12 sm:w-12"
              />
            </div>
            <div class="min-w-0 leading-tight">
              <p class="truncate font-sans font-semibold text-foreground">
                {{ title }}<span class="text-accent">.</span>
              </p>
              <p class="flex items-center gap-1.5 font-mono text-xs text-muted-foreground">
                <span
                  :class="[
                    'h-1.5 w-1.5 rounded-full',
                    llmStatus === 'online'
                      ? 'animate-pulse-dot bg-accent motion-reduce:animate-none'
                      : llmStatus === 'offline'
                        ? 'bg-destructive'
                        : 'bg-muted-foreground',
                  ]"
                />
                {{ llmStatusLabel }}
              </p>
            </div>
          </div>
          <div class="flex shrink-0 items-center gap-1">
            <button
              v-if="pinnedMessages.length > 0"
              type="button"
              @click="showPinnedList = !showPinnedList"
              :aria-pressed="showPinnedList"
              :title="$t('chatbot.pinnedMessages')"
              :class="[
                'relative flex h-8 w-8 items-center justify-center rounded-full transition-colors',
                showPinnedList
                  ? 'text-accent'
                  : 'text-muted-foreground hover:bg-muted hover:text-accent',
              ]"
            >
              <svg class="h-4 w-4" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"
                />
              </svg>
              <span
                class="absolute -top-0.5 -right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-accent text-[9px] font-bold text-accent-foreground"
              >
                {{ pinnedMessages.length }}
              </span>
            </button>
            <button
              v-if="messages.length > 0"
              type="button"
              @click="exportConversation"
              :title="$t('chatbot.exportConversation')"
              class="flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-accent"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12l4.5 4.5m0 0l4.5-4.5m-4.5 4.5V3"
                />
              </svg>
            </button>
            <button
              type="button"
              @click="onClearMessages"
              :title="$t('chatbot.clearConversation')"
              class="flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-accent"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
            </button>
            <button
              type="button"
              @click="toggleColorScheme"
              :title="
                scheme === 'dark' ? $t('chatbot.themeToggleLight') : $t('chatbot.themeToggleDark')
              "
              class="flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-accent"
            >
              <svg
                v-if="scheme === 'light'"
                class="h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                />
              </svg>
              <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                />
              </svg>
            </button>
            <button
              type="button"
              @click="toggleSoundMuted"
              :aria-pressed="soundMuted"
              :title="soundMuted ? $t('chatbot.soundUnmute') : $t('chatbot.soundMute')"
              class="flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-accent"
            >
              <svg
                v-if="soundMuted"
                class="h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17.25 9.75L19.5 12m0 0l2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z"
                />
              </svg>
              <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z"
                />
              </svg>
            </button>
            <button
              type="button"
              @click="navigateTo('/chat')"
              :title="$t('chatbot.fullscreenEnter')"
              class="flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-accent"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"
                />
              </svg>
            </button>
            <button
              v-if="showClose"
              type="button"
              @click="$emit('close')"
              :title="$t('chatbot.close')"
              class="flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-accent"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Messages -->
      <div
        ref="messagesContainerRef"
        :class="[
          'flex-1 overflow-y-auto',
          variant !== 'page' || scheme === 'dark' ? 'bg-background' : '',
        ]"
        role="log"
        aria-live="polite"
        aria-relevant="additions"
        @scroll="onMessagesScroll"
      >
        <!-- Navigation sticky (plein écran, pas de bandeau d'en-tête) -->
        <div
          v-if="variant === 'page'"
          class="sticky top-0 z-10 flex w-full items-center justify-between bg-background px-4 pt-2 pb-2 sm:bg-transparent sm:px-6 sm:pt-4"
        >
          <NuxtLink
            to="/"
            :title="$t('chatbot.backHome')"
            class="flex h-11 w-11 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-card hover:text-accent"
          >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M15 19l-7-7 7-7"
              />
            </svg>
          </NuxtLink>
          <div class="flex items-center gap-1">
            <button
              type="button"
              @click="toggleColorScheme"
              :title="
                scheme === 'dark' ? $t('chatbot.themeToggleLight') : $t('chatbot.themeToggleDark')
              "
              class="flex h-11 w-11 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-card hover:text-accent"
            >
              <svg
                v-if="scheme === 'light'"
                class="h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                />
              </svg>
              <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                />
              </svg>
            </button>
            <button
              type="button"
              @click="toggleSoundMuted"
              :aria-pressed="soundMuted"
              :title="soundMuted ? $t('chatbot.soundUnmute') : $t('chatbot.soundMute')"
              class="flex h-11 w-11 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-card hover:text-accent"
            >
              <svg
                v-if="soundMuted"
                class="h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M17.25 9.75L19.5 12m0 0l2.25 2.25M19.5 12l2.25-2.25M19.5 12l-2.25 2.25M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z"
                />
              </svg>
              <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z"
                />
              </svg>
            </button>
            <button
              v-if="pinnedMessages.length > 0"
              type="button"
              @click="showPinnedList = !showPinnedList"
              :aria-pressed="showPinnedList"
              :title="$t('chatbot.pinnedMessages')"
              :class="[
                'relative flex h-11 w-11 items-center justify-center rounded-full transition-colors',
                showPinnedList
                  ? 'text-accent'
                  : 'text-muted-foreground hover:bg-card hover:text-accent',
              ]"
            >
              <svg class="h-4 w-4" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"
                />
              </svg>
              <span
                class="absolute -top-0.5 -right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-accent text-[9px] font-bold text-accent-foreground"
              >
                {{ pinnedMessages.length }}
              </span>
            </button>
            <button
              v-if="messages.length > 0"
              type="button"
              @click="exportConversation"
              :title="$t('chatbot.exportConversation')"
              class="flex h-11 w-11 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-card hover:text-accent"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M7.5 12l4.5 4.5m0 0l4.5-4.5m-4.5 4.5V3"
                />
              </svg>
            </button>
            <button
              type="button"
              @click="onClearMessages"
              :title="$t('chatbot.clearConversation')"
              class="flex h-11 w-11 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-card hover:text-accent"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
            </button>
          </div>
        </div>
        <div class="mx-auto flex min-h-full w-full max-w-3xl flex-col space-y-3 p-4 pb-36 sm:px-6">
          <div
            v-if="isRestoringHistory"
            class="flex flex-1 flex-col justify-end gap-3"
            aria-hidden="true"
          >
            <div class="flex justify-start">
              <div
                class="h-14 w-2/3 max-w-xs animate-pulse rounded-3xl bg-card motion-reduce:animate-none"
              />
            </div>
            <div class="flex justify-end">
              <div
                class="h-10 w-1/2 max-w-[12rem] animate-pulse rounded-3xl bg-muted motion-reduce:animate-none"
              />
            </div>
            <div class="flex justify-start">
              <div
                class="h-16 w-3/4 max-w-sm animate-pulse rounded-3xl bg-card motion-reduce:animate-none"
              />
            </div>
          </div>
          <div
            v-else-if="messages.length === 0"
            class="flex flex-1 flex-col items-center justify-center gap-6 text-center"
          >
            <NuxtImg
              src="/maximejolivet.jpg"
              alt="Maxime"
              width="80"
              height="80"
              format="webp"
              class="h-14 w-14 animate-breathe rounded-full object-cover motion-reduce:animate-none sm:h-20 sm:w-20"
            />
            <div class="space-y-1.5">
              <h2 class="font-serif text-xl font-medium text-foreground">
                {{ $t('chatbot.emptyTitle') }}
              </h2>
              <p class="max-w-xs text-sm text-muted-foreground">
                {{ $t('chatbot.emptyGreeting') }}
              </p>
            </div>
            <div class="flex flex-wrap justify-center gap-2">
              <button
                v-for="suggestion in suggestedQuestions"
                :key="suggestion"
                type="button"
                class="rounded-full bg-card px-4 py-3 text-sm font-medium text-card-foreground shadow-sm shadow-foreground/5 transition-shadow hover:shadow-md"
                @click="sendMessage(suggestion)"
              >
                {{ suggestion }}
              </button>
            </div>
          </div>
          <template v-else>
            <template v-for="item in messageItems" :key="item.message.id">
              <div
                v-if="item.dateLabel"
                :class="[
                  'sticky z-10 flex justify-center py-1',
                  variant === 'page' ? 'top-12' : 'top-0',
                ]"
              >
                <span
                  class="rounded-full bg-card px-3 py-1 text-[11px] font-medium text-muted-foreground shadow-sm shadow-foreground/5"
                >
                  {{ item.dateLabel }}
                </span>
              </div>
              <MessageBubble
                :id="`msg-${item.message.id}`"
                :message="item.message"
                :is-grouped="item.isGrouped"
                :is-speaking="speakingId === item.message.id"
                :awaiting-identity="awaitingIdentity"
                :awaiting-email="awaitingEmail"
                :is-last="item.message.id === messages[messages.length - 1]?.id"
                :is-streaming="
                  isLoading &&
                  'assistant' === item.message.role &&
                  item.message.id === messages[messages.length - 1]?.id
                "
                @select-slot="onSelectSlot"
                @speak="speak"
                @feedback="setFeedback"
                @regenerate="regenerateLastReply"
                @pin="togglePin"
                @identity="onSubmitIdentity"
                @email="onSubmitEmail"
              />
            </template>
          </template>
          <TypingIndicator
            v-if="isLoading && 'assistant' !== messages[messages.length - 1]?.role"
            :label="toolCallLabel"
          />
          <div ref="messagesEndRef" />
        </div>
      </div>

      <!-- Annonce la réponse assistant complète aux lecteurs d'écran, une
           seule fois (voir le watch sur `isLoading` plus bas) -- le
           conteneur de messages ci-dessus n'annonce plus que les nouvelles
           bulles ajoutées (`aria-relevant="additions"`), jamais leur
           contenu qui se remplit progressivement. -->
      <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">
        {{ srAnnouncement }}
      </div>

      <!-- Panneau des messages épinglés, ouvert depuis le bouton dans
           l'en-tête (ou /epingles) -- cliquer un item fait défiler jusqu'à la
           bulle correspondante (déjà dans le DOM, voir le :id sur
           MessageBubble plus haut) plutôt que de la re-rendre séparément. -->
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-1"
      >
        <div
          v-if="showPinnedList"
          class="absolute bottom-20 left-3 z-30 max-h-72 w-72 overflow-y-auto rounded-2xl border border-border bg-card p-1.5 shadow-xl sm:left-4"
        >
          <div
            v-for="pinned in pinnedMessages"
            :key="pinned.id"
            class="group/pin flex items-start gap-2 rounded-xl px-2.5 py-2 hover:bg-muted"
          >
            <button type="button" class="min-w-0 flex-1 text-left" @click="jumpToPinned(pinned.id)">
              <p class="truncate text-xs font-medium text-card-foreground">
                {{ 'user' === pinned.role ? '🧑' : '🤖' }} {{ pinned.content }}
              </p>
            </button>
            <button
              type="button"
              :title="$t('messageBubble.unpin')"
              class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-muted-foreground opacity-0 transition-opacity hover:text-destructive group-hover/pin:opacity-100"
              @click="togglePin(pinned.id)"
            >
              <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
        </div>
      </Transition>

      <!-- Bouton "remonter en haut" -- symétrique de la pastille "nouveau
           message" plus bas, visible dès que le visiteur a défilé assez loin
           dans l'historique (voir onMessagesScroll). -->
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <button
          v-if="showScrollToTop"
          type="button"
          :aria-label="$t('chatbot.scrollToTop')"
          :title="$t('chatbot.scrollToTop')"
          class="absolute top-24 left-1/2 z-20 -translate-x-1/2 flex h-8 w-8 items-center justify-center rounded-full bg-card text-muted-foreground shadow-lg shadow-foreground/10 transition-colors hover:text-accent"
          @click="jumpToTop"
        >
          <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M5 10l7-7m0 0l7 7m-7-7v18"
            />
          </svg>
        </button>
      </Transition>

      <!-- Toast "connexion rétablie" -->
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-1"
      >
        <p
          v-if="showBackOnlineToast"
          class="absolute top-30 left-1/2 z-20 -translate-x-1/2 whitespace-nowrap rounded-full bg-accent px-3.5 py-1.5 text-xs font-medium text-accent-foreground shadow-lg shadow-foreground/10"
        >
          {{ $t('chatbot.backOnline') }}
        </p>
      </Transition>

      <!-- Pastille "nouveau message" -- visible seulement si le visiteur a
           remonté dans l'historique (autoScroll désactivé) et qu'un message
           est arrivé pendant ce temps -- voir onMessagesScroll/le watch sur
           messages plus bas. -->
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0 translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-1"
      >
        <button
          v-if="hasNewMessage"
          type="button"
          class="absolute bottom-20 left-1/2 z-20 -translate-x-1/2 flex items-center gap-1.5 rounded-full bg-primary px-3.5 py-1.5 text-xs font-medium text-primary-foreground shadow-lg shadow-foreground/10 transition-colors hover:bg-accent hover:text-accent-foreground"
          @click="jumpToLatest"
        >
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M19 14l-7 7m0 0l-7-7m7 7V3"
            />
          </svg>
          {{ $t('chatbot.newMessage') }}
        </button>
      </Transition>

      <!-- Astuce de découverte, bannière d'erreur et formulaire de saisie
           groupés dans un seul conteneur ancré en bas : le formulaire seul
           en `absolute bottom-0` (avant ce groupement) recouvrait ces deux
           bannières, qui restaient en flux normal juste en dessous de la
           zone de messages -- invisibles derrière lui plutôt qu'empilées
           au-dessus, cf. bannière d'erreur signalée "cachée". -->
      <div class="absolute inset-x-0 bottom-0 z-10 flex flex-col">
        <!-- Astuce de découverte (commandes slash, Cmd/Ctrl+K) -- une seule
           fois, voir maybeShowDiscoveryHint plus haut. -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 translate-y-1"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 translate-y-1"
        >
          <div
            v-if="showDiscoveryHint"
            class="border-t border-accent/20 bg-accent/5 px-4 py-2 sm:px-6"
          >
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
              <p class="flex items-center gap-1.5 text-xs text-foreground">
                <span class="shrink-0">💡</span>
                {{ $t('chatbot.discoveryHint') }}
              </p>
              <button
                type="button"
                :aria-label="$t('chatbot.close')"
                class="shrink-0 text-muted-foreground transition-colors hover:text-accent"
                @click="dismissDiscoveryHint"
              >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </div>
        </Transition>

        <!-- Erreur -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 -translate-y-1"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-1"
        >
          <div
            v-if="error"
            role="alert"
            :class="[
              'px-4 py-2 sm:px-6',
              variant !== 'page' ? 'border-t border-destructive/20 bg-destructive/10' : '',
            ]"
          >
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
              <p class="flex items-center gap-1.5 text-xs text-destructive">
                <span class="shrink-0" aria-hidden="true">⚠️</span>
                {{ error }}
              </p>
              <button
                type="button"
                class="shrink-0 whitespace-nowrap rounded-full border border-destructive/30 px-2.5 py-1 font-mono text-[11px] font-medium text-destructive transition-colors hover:bg-destructive/10"
                @click="retryLastMessage"
              >
                {{ $t('chatbot.retry') }}
              </button>
            </div>
          </div>
        </Transition>

        <!-- Saisie -->
        <form
          @submit="onSubmit"
          :class="[
            'px-3 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-6',
            variant !== 'page' ? 'border-t border-border bg-card' : '',
          ]"
        >
          <div class="relative mx-auto w-full max-w-3xl">
            <!-- Sélecteur d'emoji -->
            <Transition
              enter-active-class="transition duration-150 ease-out"
              enter-from-class="opacity-0 translate-y-2"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition duration-100 ease-in"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 translate-y-2"
            >
              <div
                v-if="showEmojiPicker"
                class="absolute bottom-full left-0 z-20 mb-2 flex h-80 w-72 flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-xl"
              >
                <div class="border-b border-border p-2">
                  <input
                    v-model="emojiSearch"
                    type="text"
                    :placeholder="$t('chatbot.emojiSearchPlaceholder')"
                    class="w-full rounded-full border-0 bg-muted px-3 py-1.5 text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-accent"
                    @keydown="onEmojiSearchKeydown"
                  />
                </div>
                <div
                  v-if="!emojiSearch"
                  class="flex shrink-0 gap-0.5 overflow-x-auto border-b border-border px-1.5 py-1.5"
                >
                  <button
                    v-for="group in emojiGroups"
                    :key="group.slug"
                    type="button"
                    :title="group.name"
                    :class="[
                      'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-base transition-colors',
                      emojiCategory === group.slug ? 'bg-primary/40' : 'hover:bg-muted',
                    ]"
                    @click="emojiCategory = group.slug"
                  >
                    {{ group.icon }}
                  </button>
                </div>
                <div
                  class="grid grid-cols-6 content-start gap-0.5 overflow-y-auto p-2"
                  role="grid"
                  @keydown="onEmojiGridKeydown"
                >
                  <p
                    v-if="emojiDataLoading"
                    class="col-span-6 mt-6 text-center text-xs text-muted-foreground"
                  >
                    {{ $t('chatbot.emojiLoading') }}
                  </p>
                  <template v-else>
                    <button
                      v-for="(item, index) in visibleEmojis"
                      :key="item.slug"
                      :ref="(el) => setEmojiButtonRef(el as Element | null, index)"
                      type="button"
                      :title="item.name"
                      :tabindex="index === focusedEmojiIndex ? 0 : -1"
                      class="flex h-9 w-9 items-center justify-center rounded-full text-lg hover:bg-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                      @click="insertEmoji(item.emoji)"
                      @focus="focusedEmojiIndex = index"
                    >
                      {{ item.emoji }}
                    </button>
                    <p
                      v-if="visibleEmojis.length === 0"
                      class="col-span-6 mt-6 text-center text-xs text-muted-foreground"
                    >
                      {{ $t('chatbot.emojiSearchEmpty') }}
                    </p>
                  </template>
                </div>
              </div>
            </Transition>

            <!-- Menu des commandes slash -->
            <Transition
              enter-active-class="transition duration-150 ease-out"
              enter-from-class="opacity-0 translate-y-2"
              enter-to-class="opacity-100 translate-y-0"
              leave-active-class="transition duration-100 ease-in"
              leave-from-class="opacity-100 translate-y-0"
              leave-to-class="opacity-0 translate-y-2"
            >
              <div
                v-if="showSlashMenu"
                role="listbox"
                class="absolute bottom-full left-0 z-20 mb-2 w-72 overflow-hidden rounded-2xl border border-border bg-card shadow-xl"
              >
                <ul class="max-h-56 overflow-y-auto p-1.5">
                  <li v-for="(command, index) in filteredSlashCommands" :key="command.name">
                    <button
                      type="button"
                      role="option"
                      :aria-selected="index === focusedSlashIndex"
                      :class="[
                        'flex w-full items-center gap-2 rounded-xl px-3 py-2 text-left transition-colors',
                        index === focusedSlashIndex
                          ? 'bg-accent/10 text-accent'
                          : 'text-foreground hover:bg-muted',
                      ]"
                      @mouseenter="focusedSlashIndex = index"
                      @click="runSlashCommand(command)"
                    >
                      <span class="shrink-0 font-mono text-xs">/{{ command.name }}</span>
                      <span class="truncate text-xs text-muted-foreground">{{
                        command.description
                      }}</span>
                    </button>
                  </li>
                  <li
                    v-if="filteredSlashCommands.length === 0"
                    class="px-3 py-4 text-center text-xs text-muted-foreground"
                  >
                    {{ $t('chatbot.slashEmpty') }}
                  </li>
                </ul>
              </div>
            </Transition>

            <!-- Barre pilule (plein écran, cf. capture goria.ai/chat) -->
            <div v-if="variant === 'page'" class="flex items-center gap-2">
              <div
                class="flex flex-1 items-center gap-1 rounded-3xl bg-card pl-5 pr-2 shadow-lg shadow-foreground/10 transition-shadow focus-within:shadow-xl"
              >
                <button
                  type="button"
                  @click="toggleEmojiPicker"
                  :aria-label="$t('chatbot.insertEmoji')"
                  class="flex h-11 w-11 shrink-0 items-center justify-center text-lg text-muted-foreground transition-colors hover:text-accent"
                >
                  🙂
                </button>
                <textarea
                  ref="textareaRef"
                  :value="inputValue"
                  @input="onInputInput"
                  @keydown="onInputKeydown"
                  rows="1"
                  :placeholder="placeholder"
                  :disabled="isLoading || awaitingIdentity || awaitingEmail"
                  class="max-h-[120px] min-w-0 flex-1 resize-none overflow-y-auto border-0 bg-transparent px-1 py-3.5 text-base text-foreground placeholder-muted-foreground focus:outline-none focus:ring-0 disabled:cursor-not-allowed disabled:opacity-50 sm:text-sm"
                ></textarea>
                <button
                  v-if="micSupported"
                  type="button"
                  @click="toggleListening"
                  :disabled="isLoading"
                  :aria-pressed="isListening"
                  :title="isListening ? $t('chatbot.micStop') : $t('chatbot.micStart')"
                  :class="[
                    'flex h-11 w-11 shrink-0 items-center justify-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                    isListening
                      ? 'animate-pulse-dot motion-reduce:animate-none text-destructive'
                      : 'text-muted-foreground hover:text-accent',
                  ]"
                >
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"
                    />
                  </svg>
                </button>
              </div>
              <button
                type="submit"
                :disabled="isLoading || awaitingIdentity || awaitingEmail || !inputValue.trim()"
                :aria-label="$t('chatbot.send')"
                class="-ml-3 z-10 flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg shadow-foreground/10 transition-colors hover:bg-accent hover:text-accent-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-40"
              >
                <svg v-if="isLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                  ></circle>
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  ></path>
                </svg>
                <svg
                  v-else
                  class="h-4 w-4 rotate-90"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                  />
                </svg>
              </button>
            </div>

            <!-- Barre classique (widget flottant) -->
            <div v-else class="flex items-center gap-2">
              <button
                type="button"
                @click="toggleEmojiPicker"
                :aria-label="$t('chatbot.insertEmoji')"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg text-muted-foreground transition-colors hover:bg-muted hover:text-accent"
              >
                🙂
              </button>
              <button
                v-if="micSupported"
                type="button"
                @click="toggleListening"
                :disabled="isLoading"
                :aria-pressed="isListening"
                :title="isListening ? $t('chatbot.micStop') : $t('chatbot.micStart')"
                :class="[
                  'flex h-9 w-9 shrink-0 items-center justify-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                  isListening
                    ? 'animate-pulse-dot motion-reduce:animate-none bg-destructive text-white'
                    : 'text-muted-foreground hover:bg-muted hover:text-accent',
                ]"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z"
                  />
                </svg>
              </button>
              <textarea
                ref="textareaRef"
                :value="inputValue"
                @input="onInputInput"
                @keydown="onInputKeydown"
                rows="1"
                :placeholder="placeholder"
                :disabled="isLoading || awaitingIdentity || awaitingEmail"
                class="max-h-[120px] min-w-0 flex-1 resize-none overflow-y-auto rounded-3xl border border-border bg-background px-4 py-2 text-base text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-accent disabled:cursor-not-allowed disabled:opacity-50 sm:text-sm"
              ></textarea>
              <button
                type="submit"
                :disabled="isLoading || awaitingIdentity || awaitingEmail || !inputValue.trim()"
                :aria-label="$t('chatbot.send')"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary font-semibold text-primary-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-card disabled:cursor-not-allowed disabled:opacity-40"
              >
                <svg v-if="isLoading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                  ></circle>
                  <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                  ></path>
                </svg>
                <svg
                  v-else
                  class="h-4 w-4 rotate-90"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                  />
                </svg>
              </button>
            </div>
            <p
              v-if="showCharCount"
              class="mt-1 text-right font-mono text-[11px] text-muted-foreground"
            >
              {{ inputValue.length }}
            </p>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { ChatbotProps } from '../types/index';

// title/placeholder defaults stay plain French literals, not $t() calls --
// withDefaults() is hoisted to module scope at compile time, so it can't
// reference a local `const { t } = useI18n()`. Not translated in practice
// either way: every current call site (StickyChatBubble.vue, pages/chat.vue)
// always passes an explicit, translated title/placeholder.
const props = withDefaults(defineProps<ChatbotProps>(), {
  title: 'Assistant IA',
  apiUrl: '/api',
  placeholder: 'Tape ton message...',
  className: '',
  showClose: false,
  variant: 'widget',
});

const { t } = useI18n();

defineEmits<{ close: [] }>();

// Astuce de découverte (commandes slash, Cmd/Ctrl+K) -- ce widget a
// accumulé plusieurs raccourcis puissants (voir slashCommands plus bas,
// et le listener Cmd/Ctrl+K de StickyChatBubble.vue) qu'un visiteur ne
// devinera jamais tout seul. Affichée une seule fois, tous visiteurs et
// sessions confondus (localStorage), après la toute première réponse
// assistant -- le visiteur est déjà engagé à ce moment-là, contrairement à
// l'ouverture du panneau où rien ne s'est encore passé. Se referme aussi
// bien manuellement, automatiquement après quelques secondes, ou dès que
// le visiteur découvre `/` par lui-même (voir le watch sur showSlashMenu
// plus bas).
const HINT_SEEN_KEY = 'chatbot:hint_seen';
const showDiscoveryHint = ref(false);
let hintShownThisSession = false;
let hintTimeout: ReturnType<typeof setTimeout> | null = null;

function dismissDiscoveryHint(): void {
  showDiscoveryHint.value = false;
  if (hintTimeout) clearTimeout(hintTimeout);
  try {
    localStorage.setItem(HINT_SEEN_KEY, '1');
  } catch {
    // Stockage indisponible (navigation privée stricte, quota) -- tant pis,
    // la prochaine réponse retentera juste de l'afficher.
  }
}

function maybeShowDiscoveryHint(): void {
  if (hintShownThisSession) return;
  hintShownThisSession = true;

  try {
    if (localStorage.getItem(HINT_SEEN_KEY)) return;
  } catch {
    return;
  }

  showDiscoveryHint.value = true;
  hintTimeout = setTimeout(dismissDiscoveryHint, 8000);
}

const {
  messages,
  isLoading,
  inputValue,
  error,
  llmStatus,
  isRestoringHistory,
  toolCallLabel,
  soundMuted,
  toggleSoundMuted,
  isOnline,
  sendMessage,
  retryLastMessage,
  regenerateLastReply,
  cancelReply,
  handleSubmit,
  handleInputChange,
  clearMessages,
  exportConversation,
  openCV,
  pinnedMessages,
  togglePin,
  messagesEndRef,
  autoScroll,
  scrollToBottom,
  setFeedback,
} = useChatbot({ apiUrl: props.apiUrl, onMessage: maybeShowDiscoveryHint });

// Groups a run of consecutive same-role messages visually (tighter spacing,
// see MessageBubble.vue's isGrouped prop) and inserts an "Aujourd'hui" /
// "Hier" / date separator whenever the day changes -- otherwise a
// conversation restored across several days (see useChatbot.ts's
// restoreConversation) reads as one undifferentiated wall of bubbles.
const isSameDay = (a: Date, b: Date) =>
  a.getFullYear() === b.getFullYear() &&
  a.getMonth() === b.getMonth() &&
  a.getDate() === b.getDate();

const formatDateSeparator = (date: Date): string => {
  const today = new Date();
  if (isSameDay(date, today)) return t('chatbot.dateToday');

  const yesterday = new Date(today);
  yesterday.setDate(today.getDate() - 1);
  if (isSameDay(date, yesterday)) return t('chatbot.dateYesterday');

  return date.toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
  });
};

const messageItems = computed(() =>
  messages.value.map((message, index) => {
    const previous = messages.value[index - 1];
    const isNewDay = !previous || !isSameDay(previous.timestamp, message.timestamp);

    return {
      message,
      dateLabel: isNewDay ? formatDateSeparator(message.timestamp) : null,
      isGrouped: !isNewDay && previous.role === message.role,
    };
  }),
);

// "Nouveau message" pill: shown only while the visitor has scrolled away
// from the bottom (autoScroll off, see useChatbot.ts) and a message arrives
// in the meantime -- an incoming reply shouldn't yank them back down while
// they're reading history.
const messagesContainerRef = ref<HTMLDivElement>();
const hasNewMessage = ref(false);
const NEAR_BOTTOM_THRESHOLD_PX = 48;

// Symmetric "jump to top" pill (jumpToTop below) -- only worth showing once
// the visitor has actually scrolled a meaningful distance down into the
// history, not for a couple of messages that already fit on screen.
const showScrollToTop = ref(false);
const SCROLL_TOP_THRESHOLD_PX = 400;

const onMessagesScroll = () => {
  const el = messagesContainerRef.value;
  if (!el) return;

  const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
  const nearBottom = distanceFromBottom < NEAR_BOTTOM_THRESHOLD_PX;
  autoScroll.value = nearBottom;
  if (nearBottom) hasNewMessage.value = false;
  showScrollToTop.value = el.scrollTop > SCROLL_TOP_THRESHOLD_PX;
};

const jumpToTop = () => {
  messagesContainerRef.value?.scrollTo({ top: 0, behavior: 'smooth' });
};

watch(
  messages,
  () => {
    if (!autoScroll.value) hasNewMessage.value = true;
  },
  { deep: true },
);

// "Connexion rétablie" toast -- useOnlineStatus() already surfaces going
// offline (the "hors ligne" error banner in useChatbot.ts), but nothing
// confirmed the reverse. Only fires on a real offline -> online transition
// during this mount (watch, not immediate), never on the initial value.
const showBackOnlineToast = ref(false);
let backOnlineToastTimeout: ReturnType<typeof setTimeout> | null = null;

// Réponse assistant annoncée aux lecteurs d'écran -- séparé du conteneur de
// messages (voir `role="log"`/`aria-relevant="additions"` dans le template)
// qui ignore désormais volontairement les mutations de texte, pour ne pas
// faire lire en rafale chaque tick de l'effet machine à écrire côté visuel
// (MessageBubble.vue::displayedContent). Cette région dédiée n'est mise à
// jour qu'une seule fois, à la bascule isLoading true -> false, avec le
// contenu final complet -- l'alternative (rien d'annoncé du tout, un live
// region mal configuré ou absent) est le bug que ce watch corrige.
const srAnnouncement = ref('');

watch(isLoading, (loading, wasLoading) => {
  if (loading || !wasLoading) return;

  const last = messages.value[messages.value.length - 1];
  if (last?.role === 'assistant' && last.content) {
    srAnnouncement.value = stripMarkdown(last.content);
  }
});

watch(isOnline, (online, wasOnline) => {
  if (!online || wasOnline) return;

  showBackOnlineToast.value = true;
  if (backOnlineToastTimeout) clearTimeout(backOnlineToastTimeout);
  backOnlineToastTimeout = setTimeout(() => {
    showBackOnlineToast.value = false;
  }, 3000);
});

onBeforeUnmount(() => {
  if (backOnlineToastTimeout) clearTimeout(backOnlineToastTimeout);
  if (hintTimeout) clearTimeout(hintTimeout);
});

// Multi-line input: a plain <textarea> that grows with its content (up to
// MAX_TEXTAREA_HEIGHT_PX, then scrolls internally) instead of the old
// single-line <input> -- pasting a code snippet or writing a longer message
// no longer scrolls the text sideways. Entrée envoie, Maj+Entrée insère un
// saut de ligne (textarea native behavior otherwise never submits on Enter).
const textareaRef = ref<HTMLTextAreaElement>();
const MAX_TEXTAREA_HEIGHT_PX = 120;

const resizeTextarea = () => {
  const el = textareaRef.value;
  if (!el) return;
  el.style.height = 'auto';
  el.style.height = `${Math.min(el.scrollHeight, MAX_TEXTAREA_HEIGHT_PX)}px`;
};

// Also used after sending -- the Enter path never loses focus on its own,
// but clicking the send button does, and the visitor is almost always
// about to type their next message.
const focusInput = () => {
  textareaRef.value?.focus();
};

// Discreet character count -- only worth showing once a message is long
// enough that its length might actually matter to the visitor, not on
// every short "merci" or "ok".
const CHAR_COUNT_THRESHOLD = 500;
const showCharCount = computed(() => inputValue.value.length > CHAR_COUNT_THRESHOLD);

const onInputInput = (e: Event) => {
  handleInputChange(e);
  resizeTextarea();
};

// Slash commands: local, non-network shortcuts to actions that already have
// a toolbar button elsewhere (clear/theme/sound/compact/fullscreen) --
// nothing here calls the backend, unlike a regular sent message. Menu opens
// as soon as the field's *entire* content is "/" + a run of non-space
// characters (no space yet), same "still composing the command name" idea
// as GitHub/Slack/Discord slash commands; typing a space or anything before
// the slash closes it, falling back to a normal message.
interface SlashCommand {
  name: string;
  description: string;
  run: () => void;
}

const slashCommands = computed<SlashCommand[]>(() => {
  const commands: SlashCommand[] = [
    { name: 'effacer', description: t('chatbot.slashClear'), run: onClearMessages },
    { name: 'theme', description: t('chatbot.slashTheme'), run: toggleColorScheme },
    { name: 'son', description: t('chatbot.slashSound'), run: toggleSoundMuted },
    { name: 'exporter', description: t('chatbot.slashExport'), run: exportConversation },
    { name: 'cv', description: t('chatbot.slashCV'), run: openCV },
    {
      name: 'epingles',
      description: t('chatbot.slashPinned'),
      run: () => {
        showPinnedList.value = !showPinnedList.value;
      },
    },
  ];
  // Only exists as a concept for the floating widget -- the 'page' variant
  // (/chat) already fills its container, see the header buttons above which
  // omit this toggle there too.
  if ('page' !== props.variant) {
    commands.push({
      name: 'ecran',
      description: t('chatbot.slashFullscreen'),
      run: () => navigateTo('/chat'),
    });
  }
  return commands;
});

const slashQuery = computed(() => /^\/(\S*)$/.exec(inputValue.value)?.[1] ?? null);
const showSlashMenu = computed(() => null !== slashQuery.value && !showEmojiPicker.value);

// The visitor found `/` on their own -- no need to keep telling them.
watch(showSlashMenu, (open) => {
  if (open && showDiscoveryHint.value) dismissDiscoveryHint();
});
const filteredSlashCommands = computed(() => {
  if (null === slashQuery.value) return [];
  const query = slashQuery.value.toLowerCase();
  return slashCommands.value.filter((command) => command.name.startsWith(query));
});

const focusedSlashIndex = ref(0);
watch(filteredSlashCommands, () => {
  focusedSlashIndex.value = 0;
});

const runSlashCommand = (command: SlashCommand) => {
  command.run();
  inputValue.value = '';
  nextTick(resizeTextarea);
  focusInput();
};

const onInputKeydown = (e: KeyboardEvent) => {
  if (showSlashMenu.value) {
    if ('ArrowDown' === e.key || 'ArrowUp' === e.key) {
      e.preventDefault();
      const step = 'ArrowDown' === e.key ? 1 : -1;
      focusedSlashIndex.value = Math.max(
        0,
        Math.min(focusedSlashIndex.value + step, filteredSlashCommands.value.length - 1),
      );
      return;
    }
    if ('Enter' === e.key && !e.shiftKey) {
      e.preventDefault();
      const command = filteredSlashCommands.value[focusedSlashIndex.value];
      if (command) runSlashCommand(command);
      return;
    }
  }

  if ('Enter' !== e.key || e.shiftKey) return;
  e.preventDefault();
  showEmojiPicker.value = false;
  sendMessage(inputValue.value);
  focusInput();
};

// inputValue is cleared programmatically (not via an @input event) once the
// message is sent -- watch it directly so the textarea collapses back to
// one line instead of staying stretched from the message that was just sent.
watch(inputValue, (value) => {
  if (!value) nextTick(resizeTextarea);
});

const jumpToLatest = () => {
  autoScroll.value = true;
  hasNewMessage.value = false;
  scrollToBottom();
};

// Pinned messages panel (header button, only shown once there's at least
// one) -- jumping to a pin scrolls the already-rendered bubble into view via
// its DOM id (see the MessageBubble :id binding above) rather than
// re-fetching/re-rendering anything.
const showPinnedList = ref(false);

// Unpinning the last item from inside the panel itself (its own unpin
// button, see the v-for below) would otherwise leave an empty panel open
// with no way to close it -- the header toggle button that normally does
// that is itself `v-if="pinnedMessages.length > 0"`, so it disappears at
// the same moment.
watch(pinnedMessages, (list) => {
  if (0 === list.length) showPinnedList.value = false;
});

const jumpToPinned = (id: string) => {
  showPinnedList.value = false;
  document.getElementById(`msg-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

// `theme` prop is now only the lowest-priority fallback -- the visitor's own

// `theme` prop is now only the lowest-priority fallback -- the visitor's own
// stored choice, then the OS `prefers-color-scheme`, both win over it. See
// composables/useColorScheme.ts.
const { scheme, toggle: toggleColorScheme } = useColorScheme(props.theme ?? 'light');
const themeClass = computed(() => (scheme.value === 'dark' ? 'dark' : ''));

const llmStatusLabel = computed(() => {
  if (llmStatus.value === 'online') return t('chatbot.statusOnline');
  if (llmStatus.value === 'offline') return t('chatbot.statusOffline');
  return t('chatbot.statusChecking');
});

// Shown on the empty-state screen before any message is sent -- pulled from
// the backend FAQ list (see composables/useFaqs.ts) so it can be managed
// from /admin/faqs instead of being hardcoded here.
const { suggestedQuestions, fetchSuggestedQuestions } = useFaqs();
onMounted(fetchSuggestedQuestions);

// Voice input: fills the text field live as the visitor dictates, they
// still press send themselves -- no auto-submit on recognition end.
const {
  isSupported: micSupported,
  isListening,
  toggleListening,
} = useSpeechRecognition((transcript) => {
  inputValue.value = transcript;
});

// Voice output: reads an assistant bubble aloud on demand (button in
// MessageBubble.vue), one utterance at a time.
const { speakingId, speak, stop: stopSpeaking } = useSpeechSynthesis();

// Otherwise clearing the thread (or closing the widget) leaves the browser
// reading a bubble that no longer exists on screen.
onBeforeUnmount(stopSpeaking);
const onClearMessages = () => {
  stopSpeaking();
  clearMessages();
};

const onSelectSlot = (iso: string, label: string) => {
  sendMessage(t('chatbot.bookSlotMessage', { label, iso }));
};

// Sends the same natural-language sentence the visitor would have typed
// themselves -- no backend change needed, the model's own tool-calling
// (enregistrer_identite) parses it exactly like free text.
const onSubmitIdentity = (firstName: string, lastName: string, email: string) => {
  sendMessage(t('chatbot.identityMessage', { firstName, lastName, email }));
};

// Same reasoning as onSubmitIdentity -- a natural sentence the model's
// tool-calling (planifier_entretien) reads exactly like free text, no
// backend change needed. Carries firstName/lastName too now (same fields
// as the identity card, see MessageBubble.vue's asksForEmail), so reuses
// the same phrasing.
const onSubmitEmail = (firstName: string, lastName: string, email: string) => {
  sendMessage(t('chatbot.identityMessage', { firstName, lastName, email }));
};

// Detected here (not in MessageBubble.vue) because the main input also
// needs to disable itself while this card is the expected way to reply --
// typing a free-text message *and* filling the card at the same time would
// be two competing paths to answer the same question. Heuristic, not a
// structured tool-call signal: the model only calls "enregistrer_identite"
// once it already has both names (see
// WorkflowExecutionService::handleSetConversation), so there's nothing
// structured to key off at the moment it's still just *asking*.
const awaitingIdentity = computed(() => {
  const last = messages.value[messages.value.length - 1];
  if (!last || 'assistant' !== last.role || isLoading.value) return false;

  const text = last.content.toLowerCase();
  return (text.includes('prénom') || text.includes('prenom')) && text.includes('?');
});

// Same reasoning as awaitingIdentity -- the model asks for this right
// before calling planifier_entretien (see the agent's system prompt), not
// via a structured signal. Excludes awaitingIdentity's own match: the
// model often asks for name and email in the same message (see
// MessageBubble.vue's asksForEmail), and the identity card already covers
// both fields, so showing both cards there would just duplicate it.
const awaitingEmail = computed(() => {
  if (awaitingIdentity.value) return false;

  const last = messages.value[messages.value.length - 1];
  if (!last || 'assistant' !== last.role || isLoading.value) return false;

  const text = last.content.toLowerCase();
  return (
    (text.includes('email') || text.includes('e-mail') || text.includes('e‑mail')) &&
    text.includes('?')
  );
});

const showEmojiPicker = ref(false);

const groupIcons: Record<string, string> = {
  smileys_emotion: '😀',
  people_body: '🧑',
  animals_nature: '🐻',
  food_drink: '🍔',
  travel_places: '✈️',
  activities: '⚽',
  objects: '💡',
  symbols: '❤️',
  flags: '🏳️',
};

interface EmojiGroup {
  slug: string;
  name: string;
  icon: string;
  emojis: Array<{ slug: string; name: string; emoji: string }>;
}

// unicode-emoji-json's full dataset is sizeable and most visitors never
// open the picker at all -- dynamic-imported on first click instead of
// bundled eagerly at module load, see composables/useChatbot.ts sibling
// pattern (data fetched on demand, not at mount) for the same reasoning.
const emojiGroups = ref<EmojiGroup[]>([]);
const emojiDataLoading = ref(false);
let emojiDataPromise: Promise<void> | null = null;

const loadEmojiData = (): Promise<void> => {
  if (emojiGroups.value.length > 0) return Promise.resolve();
  emojiDataPromise ??= (async () => {
    emojiDataLoading.value = true;
    try {
      const { default: data } = await import('unicode-emoji-json/data-by-group.json');
      emojiGroups.value = data.map((group) => ({
        slug: group.slug,
        name: group.name,
        icon: groupIcons[group.slug] ?? group.emojis[0]?.emoji ?? '❔',
        emojis: group.emojis,
      }));
      emojiCategory.value = emojiGroups.value[0]?.slug ?? '';
    } finally {
      emojiDataLoading.value = false;
    }
  })();

  return emojiDataPromise;
};

const emojiCategory = ref('');
const emojiSearch = ref('');

const allEmojis = computed(() => emojiGroups.value.flatMap((group) => group.emojis));

const visibleEmojis = computed(() => {
  const query = emojiSearch.value.trim().toLowerCase();
  if (query) {
    return allEmojis.value.filter((item) => item.name.toLowerCase().includes(query));
  }
  return emojiGroups.value.find((group) => group.slug === emojiCategory.value)?.emojis ?? [];
});

// Keyboard navigation in the emoji grid -- roving tabindex (only the
// focused button is in the tab order, matching the ARIA grid pattern)
// rather than a purely visual highlight, so it's real DOM focus: Enter/Space
// then trigger the button's own @click natively, no extra handler needed.
const EMOJI_GRID_COLUMNS = 6;
const focusedEmojiIndex = ref(0);
const emojiButtonRefs = ref<HTMLButtonElement[]>([]);

watch(visibleEmojis, () => {
  focusedEmojiIndex.value = 0;
  emojiButtonRefs.value = [];
});

const setEmojiButtonRef = (el: Element | null, index: number) => {
  if (el instanceof HTMLButtonElement) emojiButtonRefs.value[index] = el;
};

const focusEmoji = (index: number) => {
  const clamped = Math.max(0, Math.min(index, visibleEmojis.value.length - 1));
  focusedEmojiIndex.value = clamped;
  nextTick(() => emojiButtonRefs.value[clamped]?.focus());
};

const onEmojiGridKeydown = (e: KeyboardEvent) => {
  const arrowSteps: Record<string, number> = {
    ArrowRight: 1,
    ArrowLeft: -1,
    ArrowDown: EMOJI_GRID_COLUMNS,
    ArrowUp: -EMOJI_GRID_COLUMNS,
  };
  const step = arrowSteps[e.key];
  if (undefined === step) return;

  e.preventDefault();
  focusEmoji(focusedEmojiIndex.value + step);
};

// From the search field, Down/Enter jumps straight into the grid at the
// first result instead of requiring a Tab first.
const onEmojiSearchKeydown = (e: KeyboardEvent) => {
  if ('ArrowDown' !== e.key && 'Enter' !== e.key) return;
  if (0 === visibleEmojis.value.length) return;

  e.preventDefault();
  focusEmoji(0);
};

const toggleEmojiPicker = () => {
  if (!showEmojiPicker.value) {
    loadEmojiData();
  }
  showEmojiPicker.value = !showEmojiPicker.value;
};

const insertEmoji = (emoji: string) => {
  inputValue.value += emoji;
  showEmojiPicker.value = false;
};

const onSubmit = (e: Event) => {
  showEmojiPicker.value = false;
  handleSubmit(e);
  focusInput();
};

// True while `target` is already a place the visitor could be typing --
// guards the "/" shortcut below from hijacking a literal slash typed into
// the message itself or the emoji search field.
const isTypingTarget = (target: EventTarget | null): boolean => {
  const el = target as HTMLElement | null;
  return !!el && ('INPUT' === el.tagName || 'TEXTAREA' === el.tagName || el.isContentEditable);
};

// Escape closes whichever layer is "on top" -- the emoji picker first, and
// only then cancels an in-flight reply. Entrée-to-send has its own handler
// now (onInputKeydown, since a <textarea> never submits its form on Enter
// the way the old single-line <input> did natively). "/" jumps focus
// straight to the message field from anywhere else on the page
// (GitHub/Slack-style), unless the visitor is already typing somewhere.
const onKeydown = (e: KeyboardEvent) => {
  if ('/' === e.key && !isTypingTarget(e.target)) {
    e.preventDefault();
    focusInput();
    return;
  }

  if ('Escape' !== e.key) return;

  if (showEmojiPicker.value) {
    showEmojiPicker.value = false;
  } else if (showSlashMenu.value) {
    inputValue.value = '';
  } else if (showPinnedList.value) {
    showPinnedList.value = false;
  } else if (isLoading.value) {
    cancelReply();
  }
};

onMounted(() => {
  window.addEventListener('keydown', onKeydown);
  focusInput();
});
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>
