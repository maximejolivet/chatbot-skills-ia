import { ref, onBeforeUnmount } from 'vue';

// Web Speech API has no official TS lib types; SpeechRecognition here is
// the browser global (Chrome/Edge: prefixed as webkitSpeechRecognition),
// not the DOM lib type -- hence the `any` surface at the browser boundary.
export const useSpeechRecognition = (onTranscript: (transcript: string) => void) => {
  const isSupported =
    typeof window !== 'undefined' &&
    Boolean((window as any).SpeechRecognition || (window as any).webkitSpeechRecognition);

  const isListening = ref(false);
  let recognition: any = null;

  const ensureRecognition = () => {
    if (recognition || !isSupported) return recognition;

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
