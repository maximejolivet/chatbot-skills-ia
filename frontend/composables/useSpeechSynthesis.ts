import { ref, onMounted } from 'vue';

// Strips the markdown MessageBubble renders (see marked.parse there) down
// to plain prose -- reading "astérisque astérisque gras astérisque astérisque"
// aloud would defeat the point of a voice reply.
export const stripMarkdown = (markdown: string) =>
  markdown
    .replace(/```[\s\S]*?```/g, ' ')
    .replace(/`([^`]+)`/g, '$1')
    .replace(/!\[[^\]]*\]\([^)]*\)/g, '')
    .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
    .replace(/[*_~#>]+/g, '')
    .replace(/\n+/g, '. ')
    .replace(/\s{2,}/g, ' ')
    .trim();

export const useSpeechSynthesis = () => {
  // Same reasoning as useSpeechRecognition's isSupported: starts false to
  // match SSR (no `window`), flips in onMounted (client-only) instead of
  // detecting synchronously in setup() -- 'speechSynthesis' in window is
  // true in nearly every real browser, so detecting synchronously meant
  // this button was in the client's expected DOM but absent from the
  // server-rendered HTML on every single assistant message, logging a
  // "Hydration node mismatch" warning on every /chat load.
  const isSupported = ref(false);
  onMounted(() => {
    isSupported.value = 'speechSynthesis' in window;
  });
  const speakingId = ref<string | null>(null);

  const speak = (id: string, markdown: string) => {
    if (!isSupported.value) return;

    // Toggle off: clicking the button of the message currently being read
    // just stops it instead of restarting the same utterance.
    const wasSpeaking = speakingId.value === id;
    window.speechSynthesis.cancel();
    speakingId.value = null;
    if (wasSpeaking) return;

    const utterance = new SpeechSynthesisUtterance(stripMarkdown(markdown));
    utterance.lang = 'fr-FR';
    utterance.onend = () => {
      if (speakingId.value === id) speakingId.value = null;
    };
    utterance.onerror = () => {
      if (speakingId.value === id) speakingId.value = null;
    };

    speakingId.value = id;
    window.speechSynthesis.speak(utterance);
  };

  const stop = () => {
    if (!isSupported.value) return;
    window.speechSynthesis.cancel();
    speakingId.value = null;
  };

  return { isSupported, speakingId, speak, stop };
};
