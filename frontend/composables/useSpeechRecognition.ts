import { ref, onMounted, onBeforeUnmount } from 'vue';

// Web Speech API has no official TS lib types; SpeechRecognition here is
// the browser global (Chrome/Edge: prefixed as webkitSpeechRecognition),
// not the DOM lib type -- hence the `any` surface at the browser boundary.
export const useSpeechRecognition = (onTranscript: (transcript: string) => void) => {
  // Starts false (matches SSR, which has no `window`) and flips to the real
  // value in onMounted (client-only) instead of detecting synchronously in
  // setup() -- feature-detecting synchronously produced true on the client
  // vs SSR's always-false, so the mic button was present/absent depending on
  // environment and Vue logged "Hydration node mismatch" on every /chat
  // load. Flipping post-mount is a normal reactive update, not part of the
  // hydration diff, so it doesn't trigger the same warning.
  const isSupported = ref(false);
  onMounted(() => {
    isSupported.value = Boolean(
      (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition,
    );
  });

  const isListening = ref(false);
  let recognition: any = null;

  const ensureRecognition = () => {
    if (recognition || !isSupported.value) return recognition;

    const Ctor = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition;
    recognition = new Ctor();
    recognition.lang = 'fr-FR';
    recognition.interimResults = true;
    recognition.continuous = false;

    recognition.onresult = (event: any) => {
      const transcript = Array.from(event.results as any[])
        .map((result) => result[0]?.transcript ?? '')
        .join('');
      onTranscript(transcript);
    };
    recognition.onend = () => {
      isListening.value = false;
    };
    recognition.onerror = () => {
      isListening.value = false;
    };

    return recognition;
  };

  const toggleListening = () => {
    const instance = ensureRecognition();
    if (!instance) return;

    if (isListening.value) {
      instance.stop();
      return;
    }

    isListening.value = true;
    instance.start();
  };

  // Recognition keeps the mic open until stop()/onend fires -- without this
  // a component unmount (e.g. closing the widget mid-dictation) would leave
  // the browser's mic indicator on.
  onBeforeUnmount(() => recognition?.stop());

  return { isSupported, isListening, toggleListening };
};
