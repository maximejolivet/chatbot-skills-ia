<template>
  <div :class="[
    'flex gap-2',
    plain && !isUser ? 'items-start' : 'items-end',
    isUser ? 'justify-end' : 'justify-start',
    wrapperMargin,
  ]">
    <NuxtImg v-if="!isUser" src="/maximejolivet.jpg" alt="Maxime" width="36" height="36" format="webp"
      :class="[plain ? 'mt-0.5' : 'mb-1', 'h-7 w-7 shrink-0 rounded-full object-cover sm:h-9 sm:w-9']" />
    <div :class="bubbleClass">
      <div v-if="isTyping" class="flex gap-1 py-0.5">
        <span class="h-1.5 w-1.5 animate-bounce-slow rounded-full bg-current motion-reduce:animate-none" />
        <span class="h-1.5 w-1.5 animate-bounce-slow rounded-full bg-current motion-reduce:animate-none"
          style="animation-delay: 0.1s" />
        <span class="h-1.5 w-1.5 animate-bounce-slow rounded-full bg-current motion-reduce:animate-none"
          style="animation-delay: 0.2s" />
      </div>
      <div v-else
        class="prose prose-sm max-w-none text-inherit font-sans text-sm leading-relaxed prose-headings:font-sans prose-headings:font-semibold prose-headings:text-inherit prose-p:my-1 prose-p:text-inherit prose-strong:text-inherit prose-a:text-inherit prose-a:underline prose-a:decoration-dotted prose-a:underline-offset-2 prose-code:text-inherit prose-table:my-2 prose-pre:my-2 prose-ul:my-1 prose-ol:my-1"
        v-html="formattedContent" @click="onContentClick" />
      <span v-if="isStreaming && !isTyping"
        class="animate-blink -mb-0.5 ml-0.5 inline-block h-3.5 w-[2px] bg-current align-middle motion-reduce:animate-none"
        aria-hidden="true" />
      <LinkPreviewCard v-for="link in previewLinks" :key="link" :url="link" />
      <div class="mt-1 flex items-center gap-2">
        <p :class="['font-mono text-[10px]', isUser ? 'text-accent-foreground/70' : 'text-muted-foreground']">
          {{ formattedTime }}
        </p>
        <button v-if="!isTyping" type="button" :aria-pressed="message.pinned"
          :title="message.pinned ? $t('messageBubble.unpin') : $t('messageBubble.pin')" :class="[
            'flex h-5 w-5 items-center justify-center rounded-full transition-all sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100',
            message.pinned
              ? isUser
                ? 'text-accent-foreground sm:opacity-100'
                : 'text-accent sm:opacity-100'
              : isUser
                ? 'text-accent-foreground/70 hover:text-accent-foreground'
                : 'text-muted-foreground hover:text-accent',
          ]" @click="$emit('pin', message.id)">
          <svg class="h-3.5 w-3.5" :fill="message.pinned ? 'currentColor' : 'none'" stroke="currentColor"
            viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z" />
          </svg>
        </button>
        <button v-if="!isUser && !isTyping && speechSupported" type="button" :aria-pressed="isSpeaking"
          :title="isSpeaking ? $t('messageBubble.speakStop') : $t('messageBubble.speakStart')"
          class="flex h-5 w-5 items-center justify-center rounded-full text-muted-foreground transition-all hover:text-accent sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
          @click="$emit('speak', message.id, message.content)">
          <svg v-if="!isSpeaking" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M11 5L6 9H2v6h4l5 4V5zM19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07" />
          </svg>
          <svg v-else class="h-3.5 w-3.5 animate-pulse-dot motion-reduce:animate-none" fill="none" stroke="currentColor"
            viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M11 5L6 9H2v6h4l5 4V5zM17 9l4 6m0-6l-4 6" />
          </svg>
        </button>
        <button v-if="!isUser && !isTyping" type="button"
          :title="copied ? $t('messageBubble.copied') : $t('messageBubble.copy')" :class="[
            'flex h-5 w-5 items-center justify-center rounded-full text-muted-foreground transition-all hover:text-accent sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100',
            copied ? 'sm:opacity-100' : '',
          ]" @click="copyToClipboard">
          <svg v-if="!copied" class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <svg v-else class="h-3.5 w-3.5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </button>
        <button v-if="!isUser && !isTyping" type="button" :aria-pressed="message.feedback === 'positive'"
          :title="$t('messageBubble.feedbackPositive')" :class="[
            'flex h-5 w-5 items-center justify-center rounded-full text-xs leading-none opacity-70 transition-all hover:opacity-100 sm:opacity-0 sm:group-hover:opacity-70 sm:group-focus-within:opacity-70',
            message.feedback === 'positive' ? 'bg-accent/10 opacity-100 sm:opacity-100' : '',
          ]" @click="
            $emit('feedback', message.id, message.feedback === 'positive' ? null : 'positive')
            ">
          👍
        </button>
        <button v-if="!isUser && !isTyping" type="button" :aria-pressed="message.feedback === 'negative'"
          :title="$t('messageBubble.feedbackNegative')" :class="[
            'flex h-5 w-5 items-center justify-center rounded-full text-xs leading-none opacity-70 transition-all hover:opacity-100 sm:opacity-0 sm:group-hover:opacity-70 sm:group-focus-within:opacity-70',
            message.feedback === 'negative' ? 'bg-destructive/10 opacity-100 sm:opacity-100' : '',
          ]" @click="
            $emit('feedback', message.id, message.feedback === 'negative' ? null : 'negative')
            ">
          👎
        </button>
        <button v-if="!isUser && !isTyping && isLast" type="button" :title="$t('messageBubble.regenerate')"
          class="flex h-5 w-5 items-center justify-center rounded-full text-muted-foreground transition-all hover:text-accent sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
          @click="$emit('regenerate')">
          <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>
      </div>
      <div v-if="asksForIdentity" class="mt-2 flex flex-col gap-2 rounded-xl border border-border bg-transparent p-2.5">
        <div class="flex gap-2">
          <div class="relative min-w-0 flex-1">
            <input v-model="identityFirstName" type="text" placeholder=" " :class="floatingInputClass"
              @keydown.enter="submitIdentity" />
            <label :class="floatingLabelClass">{{ $t('messageBubble.identityFirstName') }}</label>
          </div>
          <div class="relative min-w-0 flex-1">
            <input v-model="identityLastName" type="text" placeholder=" " :class="floatingInputClass"
              @keydown.enter="submitIdentity" />
            <label :class="floatingLabelClass">{{ $t('messageBubble.identityLastName') }}</label>
          </div>
        </div>
        <div class="relative">
          <input v-model="identityEmail" type="email" placeholder=" " :class="floatingInputClass"
            @keydown.enter="submitIdentity" />
          <label :class="floatingLabelClass">{{ $t('messageBubble.emailPlaceholder') }}</label>
        </div>
        <div class="relative">
          <input v-model="identityObjet" type="text" placeholder=" " :class="floatingInputClass"
            @keydown.enter="submitIdentity" />
          <label :class="floatingLabelClass">{{ $t('messageBubble.identityObjetPlaceholder') }}</label>
        </div>
        <div class="flex gap-1.5">
          <button type="button" :class="modaliteButtonClass('visio' === identityModalite)"
            @click="identityModalite = 'visio'">
            {{ $t('messageBubble.modaliteVisio') }}
          </button>
          <button type="button" :class="modaliteButtonClass('telephone' === identityModalite)"
            @click="identityModalite = 'telephone'">
            {{ $t('messageBubble.modaliteTelephone') }}
          </button>
        </div>
        <div v-if="'telephone' === identityModalite" class="relative">
          <input v-model="identityTelephone" type="tel" placeholder=" " :class="floatingInputClass"
            @keydown.enter="submitIdentity" />
          <label :class="floatingLabelClass">{{ $t('messageBubble.identityTelephonePlaceholder') }}</label>
        </div>
        <div class="flex gap-2">
          <label class="flex min-w-0 flex-1 flex-col gap-0.5 text-[11px] text-muted-foreground">
            {{ $t('messageBubble.identityDatePlaceholder') }}
            <input v-model="identityDateOnly" type="date" :min="minDateValue"
              class="rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-accent"
              @keydown.enter="submitIdentity" />
          </label>
          <label class="flex min-w-0 flex-1 flex-col gap-0.5 text-[11px] text-muted-foreground">
            {{ $t('messageBubble.identityTimePlaceholder') }}
            <input v-model="identityTimeOnly" type="time" :min="identityTimeMin" :step="DATE_STEP_SECONDS"
              class="rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-accent"
              @keydown.enter="submitIdentity" />
          </label>
        </div>
        <p v-if="identityDateTime && isWeekend(identityDateTime)" class="text-[11px] text-destructive">
          {{ $t('messageBubble.weekendHint') }}
        </p>
        <p v-else-if="identityDateTime && !isWithinBusinessHours(identityDateTime)"
          class="text-[11px] text-destructive">
          {{ $t('messageBubble.businessHoursHint') }}
        </p>
        <button type="button" :disabled="!isIdentityValid"
          class="self-end rounded-full bg-primary px-3 py-1 font-mono text-xs font-semibold text-primary-foreground transition-colors hover:bg-accent hover:text-accent-foreground disabled:cursor-not-allowed disabled:opacity-40"
          @click="submitIdentity">
          {{ $t('messageBubble.identityValidate') }}
        </button>
      </div>
      <div v-if="asksForEmail" class="mt-2 flex flex-col gap-2 rounded-xl border border-border bg-transparent p-2.5">
        <div class="flex gap-2">
          <div class="relative min-w-0 flex-1">
            <input v-model="emailFirstName" type="text" placeholder=" " :class="floatingInputClass"
              @keydown.enter="submitEmail" />
            <label :class="floatingLabelClass">{{ $t('messageBubble.identityFirstName') }}</label>
          </div>
          <div class="relative min-w-0 flex-1">
            <input v-model="emailLastName" type="text" placeholder=" " :class="floatingInputClass"
              @keydown.enter="submitEmail" />
            <label :class="floatingLabelClass">{{ $t('messageBubble.identityLastName') }}</label>
          </div>
        </div>
        <div class="relative">
          <input v-model="emailValue" type="email" placeholder=" " :class="floatingInputClass"
            @keydown.enter="submitEmail" />
          <label :class="floatingLabelClass">{{ $t('messageBubble.emailPlaceholder') }}</label>
        </div>
        <div class="relative">
          <input v-model="emailObjet" type="text" placeholder=" " :class="floatingInputClass"
            @keydown.enter="submitEmail" />
          <label :class="floatingLabelClass">{{ $t('messageBubble.identityObjetPlaceholder') }}</label>
        </div>
        <div class="flex gap-1.5">
          <button type="button" :class="modaliteButtonClass('visio' === emailModalite)"
            @click="emailModalite = 'visio'">
            {{ $t('messageBubble.modaliteVisio') }}
          </button>
          <button type="button" :class="modaliteButtonClass('telephone' === emailModalite)"
            @click="emailModalite = 'telephone'">
            {{ $t('messageBubble.modaliteTelephone') }}
          </button>
        </div>
        <div v-if="'telephone' === emailModalite" class="relative">
          <input v-model="emailTelephone" type="tel" placeholder=" " :class="floatingInputClass"
            @keydown.enter="submitEmail" />
          <label :class="floatingLabelClass">{{ $t('messageBubble.identityTelephonePlaceholder') }}</label>
        </div>
        <p v-if="emailDateTime && isWeekend(emailDateTime)" class="text-[11px] text-destructive">
          {{ $t('messageBubble.weekendHint') }}
        </p>
        <p v-else-if="emailDateTime && !isWithinBusinessHours(emailDateTime)" class="text-[11px] text-destructive">
          {{ $t('messageBubble.businessHoursHint') }}
        </p>
        <div class="flex items-end gap-2">
          <label class="flex min-w-0 flex-1 flex-col gap-0.5 text-[11px] text-muted-foreground">
            {{ $t('messageBubble.identityDatePlaceholder') }}
            <input v-model="emailDateOnly" type="date" :min="minDateValue"
              class="rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-accent"
              @keydown.enter="submitEmail" />
          </label>
          <label class="flex min-w-0 flex-1 flex-col gap-0.5 text-[11px] text-muted-foreground">
            {{ $t('messageBubble.identityTimePlaceholder') }}
            <input v-model="emailTimeOnly" type="time" :min="emailTimeMin" :step="DATE_STEP_SECONDS"
              class="rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-accent"
              @keydown.enter="submitEmail" />
          </label>
          <button type="button" :disabled="!isEmailFormValid"
            class="shrink-0 rounded-full bg-primary px-3 py-1 font-mono text-xs font-semibold text-primary-foreground transition-colors hover:bg-accent hover:text-accent-foreground disabled:cursor-not-allowed disabled:opacity-40"
            @click="submitEmail">
            {{ $t('messageBubble.identityValidate') }}
          </button>
        </div>
      </div>
      <div v-if="bookingConfirmation"
        class="mt-2 flex animate-celebrate items-center gap-2 rounded-xl border border-accent/30 bg-accent/10 px-3 py-2 motion-reduce:animate-none">
        <span class="text-base">✅</span>
        <p class="font-mono text-xs text-card-foreground">
          {{ $t('messageBubble.interviewConfirmed', { name: bookingConfirmation.attendeeName })
          }}<br />
          {{ bookingConfirmation.label }}
        </p>
      </div>
      <p v-if="debugMode && message.tokenUsage" class="mt-1.5 font-mono text-[10px] text-muted-foreground"
        :title="$t('messageBubble.debugModeHint')">
        🔧 {{ message.tokenUsage.total_tokens }} tokens ({{ message.tokenUsage.prompt_tokens }}↑/{{
          message.tokenUsage.completion_tokens
        }}↓) · {{ message.tokenUsage.provider }}/{{ message.tokenUsage.model }}
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { marked } from 'marked';
import DOMPurify from 'isomorphic-dompurify';
import type { InterviewBookingSubmission, Message } from '../types/index';

interface Props {
  message: Message;
  isSpeaking?: boolean;
  isLast?: boolean;
  isStreaming?: boolean;
  // True when the previous message in the thread is from the same role --
  // tightens the gap above this bubble (see the wrapper div's class) so a
  // rapid-fire run of same-sender messages reads as one group, not N
  // separately-spaced bubbles.
  isGrouped?: boolean;
  // Computed by Chatbot.vue (not locally, see asksForIdentity below) --
  // it also needs this to disable the main input while the identity card
  // is the expected way to reply, so the detection lives in one place.
  awaitingIdentity?: boolean;
  // Same reasoning as awaitingIdentity, same place (Chatbot.vue) -- see
  // asksForEmail below.
  awaitingEmail?: boolean;
  // Chatbot.vue passes this for the full-page /chat variant only (its
  // "Studio" layout, see the <aside> identity panel there): drops the
  // assistant bubble's card background/shadow for a plain avatar+text read,
  // and swaps the user bubble's text color from the widget's hardcoded
  // white to the accent-foreground token (always ink -- see main.css) to
  // match. The widget keeps its original bubble-both-ways look untouched.
  plain?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
  speak: [id: string, content: string];
  feedback: [id: string, feedback: 'positive' | 'negative' | null];
  regenerate: [];
  pin: [id: string];
  identity: [submission: InterviewBookingSubmission];
  email: [submission: InterviewBookingSubmission];
}>();

const { isSupported: speechSupported } = useSpeechSynthesis();
const debugMode = useDebugMode();
const { t } = useI18n();

// Raw markdown, not the rendered plain text -- preserves formatting if
// pasted somewhere else that also understands markdown.
const copied = ref(false);
let copiedTimeout: ReturnType<typeof setTimeout> | null = null;

const copyToClipboard = async () => {
  try {
    await navigator.clipboard.writeText(props.message.content);
  } catch {
    // Clipboard API unavailable or permission denied -- no-op, the button
    // simply doesn't show the "copied" feedback.
    return;
  }
  copied.value = true;
  if (copiedTimeout) clearTimeout(copiedTimeout);
  copiedTimeout = setTimeout(() => {
    copied.value = false;
  }, 1500);
};

onBeforeUnmount(() => {
  if (copiedTimeout) clearTimeout(copiedTimeout);
});

const isUser = computed(() => props.message.role === 'user');
const isTyping = computed(() => props.message.isTyping);
// accent-foreground is always ink (see main.css) -- the mint bubble needs
// that dark text regardless of variant, white read too low-contrast on it.
const bubbleClass = computed(() => [
  'group max-w-[80%] px-4 py-2.5',
  isUser.value
    ? 'rounded-3xl bg-accent text-accent-foreground'
    : props.plain
      ? 'rounded-none bg-transparent px-0 py-0 text-foreground'
      : 'rounded-3xl bg-card text-card-foreground shadow-sm shadow-foreground/5',
]);
const wrapperMargin = computed(() => (props.isGrouped ? 'mb-1' : 'mb-3'));
const formattedTime = computed(() =>
  props.message.timestamp.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
);

// The actual "does this message ask for identity" heuristic lives in
// Chatbot.vue (see awaitingIdentity there) -- it also needs the answer to
// disable the main input while the card below is the expected way to
// reply, so detection happens once, in one place, rather than
// independently here too. This only narrows *which* bubble renders the
// card: the current last assistant one, never restored history further up
// the thread, and never mid-stream (the sentence isn't necessarily
// complete yet).
const asksForIdentity = computed(
  () =>
    Boolean(props.awaitingIdentity) &&
    !isUser.value &&
    !isTyping.value &&
    props.isLast &&
    !props.isStreaming,
);

// Floating label: the label sits centered over the input like a placeholder
// until there's a value, then shrinks and moves to the top-left corner.
// `placeholder=" "` (a single space, not empty) is required for
// `:placeholder-shown` to fire in every browser. `peer-focus` overrides
// `peer-placeholder-shown` with `!` so the label still floats up while
// focused-but-empty, regardless of Tailwind's generated rule order.
const floatingInputClass =
  'peer w-full rounded-lg border border-border bg-background px-2.5 pb-1.5 pt-4 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-accent';
const floatingLabelClass =
  'pointer-events-none absolute left-2.5 top-1 text-[9px] text-muted-foreground transition-all duration-150 peer-placeholder-shown:top-1/2 peer-placeholder-shown:-translate-y-1/2 peer-placeholder-shown:text-xs peer-focus:!top-1 peer-focus:!translate-y-0 peer-focus:!text-[9px]';

// Visio/telephone segmented toggle -- active side gets the accent
// treatment, inactive side stays muted, same look across both cards.
const modaliteButtonClass = (active: boolean) => [
  'rounded-full border px-2.5 py-0.5 font-mono text-[11px] font-semibold transition-colors',
  active
    ? 'border-accent bg-accent/10 text-accent'
    : 'border-border text-muted-foreground hover:border-accent hover:text-accent',
];

// Deliberately permissive (no RFC 5322 edge cases) -- this only guards
// against obvious typos before the sentence reaches the model, which is
// the one that actually has to make sense of it; the real check of
// record is whatever Cal.eu itself does with the address.
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const isValidEmail = (value: string) => EMAIL_PATTERN.test(value);

// Date and time are two separate native inputs (day/month/year wheel vs.
// hour/minute wheel is a much faster picker on mobile than one combined
// datetime-local field). Combined back into the same "YYYY-MM-DDTHH:mm"
// local-time string `formatDateTimeLocal`/`isWithinBusinessHours` already
// expect, so that logic doesn't need to know the fields were ever split.
const pad2 = (n: number) => String(n).padStart(2, '0');
const now = new Date();
const minDateValue = `${now.getFullYear()}-${pad2(now.getMonth() + 1)}-${pad2(now.getDate())}`;
const minTimeValue = `${pad2(now.getHours())}:${pad2(now.getMinutes())}`;
const combineDateTime = (date: string, time: string): string => (date && time ? `${date}T${time}` : '');

const formatDateTimeLocal = (value: string): string => {
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return value;
  return parsed.toLocaleString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    hour: '2-digit',
    minute: '2-digit',
  });
};

// `step` snaps the time picker's minute wheel to 30-minute increments,
// matching how Cal.eu actually offers slots (see
// "lister_creneaux_disponibles"). The business-hours windows themselves
// (morning + afternoon, split around Maxime's lunch break) are enforced
// here in JS, not by the picker.
const DATE_STEP_SECONDS = 1800;
const BUSINESS_HOURS_WINDOWS = [
  { start: 9 * 60, end: 12 * 60 + 30 },
  { start: 13 * 60 + 30, end: 19 * 60 + 30 },
];

const isWithinBusinessHours = (value: string): boolean => {
  if (!value) return false;
  const [, timePart] = value.split('T');
  if (!timePart) return false;
  const [hours, minutes] = timePart.split(':').map(Number);
  const totalMinutes = hours * 60 + minutes;
  return BUSINESS_HOURS_WINDOWS.some(
    ({ start, end }) => totalMinutes >= start && totalMinutes <= end,
  );
};

// Native `<input type="date">` has no way to grey out specific weekdays in
// its picker (only a full custom calendar widget could) -- caught here
// instead, same pattern as isWithinBusinessHours: block submit and show a
// hint rather than prevent the pick itself. `T00:00:00` forces local-time
// parsing (a bare "YYYY-MM-DD" is parsed as UTC by `Date`, which can land
// on the wrong day in a negative UTC offset).
const isWeekend = (value: string): boolean => {
  const [datePart] = value.split('T');
  if (!datePart) return false;
  const day = new Date(`${datePart}T00:00:00`).getDay();
  return day === 0 || day === 6;
};

const isBookableDateTime = (value: string): boolean =>
  isWithinBusinessHours(value) && !isWeekend(value);

const identityFirstName = ref('');
const identityLastName = ref('');
const identityEmail = ref('');
const identityObjet = ref('');
const identityDateOnly = ref('');
const identityTimeOnly = ref('');
const identityDateTime = computed(() => combineDateTime(identityDateOnly.value, identityTimeOnly.value));
// Only meaningful (and only bound in the template) once today is picked --
// otherwise a future date would inherit today's current-time floor.
const identityTimeMin = computed(() =>
  identityDateOnly.value === minDateValue ? minTimeValue : undefined,
);
// Phone number only matters (and is only shown, see the template) for a
// call -- required in that case, same reasoning as adresse used to be.
const identityModalite = ref<'visio' | 'telephone'>('visio');
const identityTelephone = ref('');

const isIdentityValid = computed(
  () =>
    Boolean(identityFirstName.value.trim()) &&
    Boolean(identityLastName.value.trim()) &&
    isValidEmail(identityEmail.value.trim()) &&
    Boolean(identityObjet.value.trim()) &&
    isBookableDateTime(identityDateTime.value) &&
    ('visio' === identityModalite.value || Boolean(identityTelephone.value.trim())),
);

const submitIdentity = () => {
  const firstName = identityFirstName.value.trim();
  const lastName = identityLastName.value.trim();
  const email = identityEmail.value.trim();
  const objet = identityObjet.value.trim();
  const telephone = identityTelephone.value.trim();
  if (
    !firstName ||
    !lastName ||
    !isValidEmail(email) ||
    !objet ||
    !isBookableDateTime(identityDateTime.value) ||
    ('telephone' === identityModalite.value && !telephone)
  )
    return;

  emit('identity', {
    firstName,
    lastName,
    email,
    objet,
    date: formatDateTimeLocal(identityDateTime.value),
    modalite: identityModalite.value,
    telephone,
  });
};

// Fallback for the identity/email the model asks for right before
// planifier_entretien (see the agent's system prompt), for a visitor who
// answered the identity card above by typing free text instead of using it
// (so nothing structured was collected yet) -- same fields as
// asksForIdentity above, asked again here in case the name wasn't actually
// captured, see submitIdentity.
const asksForEmail = computed(
  () =>
    Boolean(props.awaitingEmail) &&
    !isUser.value &&
    !isTyping.value &&
    props.isLast &&
    !props.isStreaming,
);

const emailFirstName = ref('');
const emailLastName = ref('');
const emailValue = ref('');
const emailObjet = ref('');
const emailDateOnly = ref('');
const emailTimeOnly = ref('');
const emailDateTime = computed(() => combineDateTime(emailDateOnly.value, emailTimeOnly.value));
const emailTimeMin = computed(() => (emailDateOnly.value === minDateValue ? minTimeValue : undefined));
// Same reasoning as identityModalite/identityTelephone above.
const emailModalite = ref<'visio' | 'telephone'>('visio');
const emailTelephone = ref('');

const isEmailFormValid = computed(
  () =>
    Boolean(emailFirstName.value.trim()) &&
    Boolean(emailLastName.value.trim()) &&
    isValidEmail(emailValue.value.trim()) &&
    Boolean(emailObjet.value.trim()) &&
    isBookableDateTime(emailDateTime.value) &&
    ('visio' === emailModalite.value || Boolean(emailTelephone.value.trim())),
);

const submitEmail = () => {
  const firstName = emailFirstName.value.trim();
  const lastName = emailLastName.value.trim();
  const email = emailValue.value.trim();
  const objet = emailObjet.value.trim();
  const telephone = emailTelephone.value.trim();
  if (
    !firstName ||
    !lastName ||
    !isValidEmail(email) ||
    !objet ||
    !isBookableDateTime(emailDateTime.value) ||
    ('telephone' === emailModalite.value && !telephone)
  )
    return;

  emit('email', {
    firstName,
    lastName,
    email,
    objet,
    date: formatDateTimeLocal(emailDateTime.value),
    modalite: emailModalite.value,
    telephone,
  });
};

// "planifier_entretien" books directly on Cal.eu and its raw output (API
// response, internal workflow/tool names) isn't fit for a visitor-facing
// chat. Only trust our own known argument schema (start_time,
// attendee_name -- see the Workflow's parametersSchema), never fields from
// inside the Cal.eu response itself.
const bookingConfirmation = computed(() => {
  const call = props.message.toolCalls?.find(
    (c) => 'planifier_entretien' === c.tool && 'completed' === c.status,
  );
  const args = call?.arguments as { start_time?: string; attendee_name?: string } | undefined;
  if (!args?.start_time || !args?.attendee_name) return null;

  return {
    attendeeName: args.attendee_name,
    label: new Date(args.start_time).toLocaleString('fr-FR', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      hour: '2-digit',
      minute: '2-digit',
    }),
  };
});

// Le LLM répond régulièrement en Markdown complet (tableaux, listes,
// titres, gras...), pas juste du **gras** occasionnel -- marked le parse
// (GFM activé par défaut : tableaux, listes à puces, etc.) puis DOMPurify
// assainit le HTML avant l'injection via v-html, le contenu venant d'une
// source non fiable (LLM/RAG sur des documents uploadés).
//
// Bouton "copier" par bloc de code : le rendu passe par v-html, donc pas de
// gestionnaire Vue possible sur le bouton lui-même (DOMPurify retire de
// toute façon les attributs on*) -- on garde le rendu par défaut de chaque
// bloc (échappement HTML correct, géré par marked) et on l'enveloppe d'un
// bouton, lu au clic par délégation d'événement (@click sur le conteneur
// v-html, voir onContentClick) plutôt que par un data-attribute : le texte
// exact du bloc est déjà dans le DOM une fois rendu, pas besoin de le
// dupliquer/encoder.
const defaultRenderer = new marked.Renderer();
const renderer = new marked.Renderer();
renderer.code = (token) => {
  const codeHtml = defaultRenderer.code(token);

  return `<div class="code-block-wrapper relative group/code">${codeHtml}<button type="button" class="code-copy-button absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-md bg-white/10 text-slate-300 opacity-100 backdrop-blur-sm transition-opacity hover:bg-white/20 hover:text-white sm:opacity-0 sm:group-hover/code:opacity-100" aria-label="${t('messageBubble.copyCode')}"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button></div>`;
};

// A wide table (more columns than the bubble's max-w-[80%] fits) would
// otherwise overflow the bubble outright -- `display: block` on <table>
// itself would break the table layout algorithm, so this wraps the
// already-rendered <table> in a scrolling <div> instead, same
// wrap-the-default-output technique as the code renderer above. Unlike
// code(), table() renders inline markdown inside each cell (bold, links...)
// via `this.parser.parseInline()` -- `defaultRenderer.parser` is only ever
// set by marked's own Parser on the renderer it's actually using (`renderer`
// below), never on this standalone instance, so it has to be copied over
// by hand or every table crashes with "Cannot read properties of
// undefined (reading 'parseInline')".
renderer.table = (token) => {
  defaultRenderer.parser = renderer.parser;
  const tableHtml = defaultRenderer.table(token);

  return `<div class="overflow-x-auto">${tableHtml}</div>`;
};

marked.setOptions({ gfm: true, breaks: true, renderer });

// Real typewriter reveal: real token deltas (see useChatbot.ts) can arrive in
// uneven bursts -- a whole word, sometimes several at once -- which reads as
// jumpy rather than "typed". While streaming, `displayedContent` catches up
// to the true `message.content` at a fixed pace instead of jumping straight
// to it each time, decoupling the visual reveal from network/token timing.
// Markdown is parsed from this lagging snapshot below; once streaming ends
// it snaps to the full text immediately so nothing is left trailing behind.
const TYPEWRITER_CHARS_PER_TICK = 3;
const TYPEWRITER_TICK_MS = 20;

const displayedContent = ref(props.message.content);
let typewriterTimer: ReturnType<typeof setInterval> | null = null;

const stopTypewriter = () => {
  if (typewriterTimer) clearInterval(typewriterTimer);
  typewriterTimer = null;
};

const startTypewriter = () => {
  if (typewriterTimer) return;
  typewriterTimer = setInterval(() => {
    const target = props.message.content;
    if (displayedContent.value.length >= target.length) {
      stopTypewriter();
      return;
    }
    displayedContent.value = target.slice(
      0,
      displayedContent.value.length + TYPEWRITER_CHARS_PER_TICK,
    );
  }, TYPEWRITER_TICK_MS);
};

watch(
  () => props.message.content,
  () => {
    if (props.isStreaming) {
      startTypewriter();
    } else {
      // Not streaming (restored history, a snap on isStreaming turning off
      // below, ...) -- show the full content immediately, no artificial lag.
      stopTypewriter();
      displayedContent.value = props.message.content;
    }
  },
);

watch(
  () => props.isStreaming,
  (streaming) => {
    if (streaming) return;
    stopTypewriter();
    displayedContent.value = props.message.content;
  },
);

onBeforeUnmount(stopTypewriter);

const formattedContent = computed(() =>
  DOMPurify.sanitize(marked.parse(displayedContent.value, { async: false }) as string),
);

// Link preview cards (LinkPreviewCard.vue): a plain regex over the already
// sanitized HTML string, not a DOM parser -- this computed can run
// server-side too, where `document`/`DOMParser` aren't available. Capped
// at 3 and skipped entirely while streaming: a URL isn't necessarily
// complete yet mid-stream, and fetching a preview for a link that's still
// growing character by character would be wasted (or wrong) work.
const MAX_PREVIEW_LINKS = 3;

const previewLinks = computed(() => {
  if (props.isStreaming) return [];

  const hrefs = new Set<string>();
  const hrefPattern = /<a\s[^>]*href=["']([^"']+)["']/gi;
  let match: RegExpExecArray | null;
  while ((match = hrefPattern.exec(formattedContent.value)) && hrefs.size < MAX_PREVIEW_LINKS) {
    if (/^https?:\/\//i.test(match[1])) hrefs.add(match[1]);
  }

  return [...hrefs];
});

const CHECK_ICON =
  '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>';

const onContentClick = async (event: MouseEvent) => {
  const button = (event.target as HTMLElement).closest<HTMLElement>('.code-copy-button');
  if (!button) return;

  const code = button.closest('.code-block-wrapper')?.querySelector('code');
  if (!code?.textContent) return;

  try {
    await navigator.clipboard.writeText(code.textContent);
  } catch {
    return;
  }

  // Imperative, not Vue-reactive: this button lives inside v-html content,
  // outside Vue's render tree -- same constraint as the delegation above.
  const originalHtml = button.innerHTML;
  button.innerHTML = CHECK_ICON;
  setTimeout(() => {
    button.innerHTML = originalHtml;
  }, 1500);
};
</script>
