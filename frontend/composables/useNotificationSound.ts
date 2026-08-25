let audioContext: AudioContext | undefined;

const MUTED_STORAGE_KEY = 'chatbot:sound_muted';

const readMuted = (): boolean => {
  try {
    return 'true' === localStorage.getItem(MUTED_STORAGE_KEY);
  } catch {
    // localStorage unavailable (private mode, disabled) -- not muted by
    // default, same as never having chosen to mute.
    return false;
  }
};

/**
 * @param notes Frequencies (Hz) with their offset (seconds) from the chime's start.
 */
function playChime(notes: Array<{ freq: number; at: number }>, duration: number, peakGain: number) {
  if (typeof window === 'undefined') return;

  audioContext ??= new AudioContext();
  if (audioContext.state === 'suspended') audioContext.resume();

  const now = audioContext.currentTime;
  const oscillator = audioContext.createOscillator();
  const gain = audioContext.createGain();

  oscillator.type = 'sine';
  for (const note of notes) oscillator.frequency.setValueAtTime(note.freq, now + note.at);

  gain.gain.setValueAtTime(0, now);
  gain.gain.linearRampToValueAtTime(peakGain, now + 0.01);
  gain.gain.exponentialRampToValueAtTime(0.001, now + duration);

  oscillator.connect(gain);
  gain.connect(audioContext.destination);

  oscillator.start(now);
  oscillator.stop(now + duration);
}

export const useNotificationSound = () => {
  // Starts unmuted for SSR/first paint, corrected in onMounted -- same
  // localStorage-hydration tradeoff as useColorScheme.ts (a possible
  // one-frame flash rather than a blocking inline script).
  const muted = ref(false);

  onMounted(() => {
    muted.value = readMuted();
  });

  const toggleMuted = () => {
    muted.value = !muted.value;
    try {
      localStorage.setItem(MUTED_STORAGE_KEY, String(muted.value));
    } catch {
      // Toggle still works for this session, just doesn't persist.
    }
  };

  const playMessageSound = () => {
    if (muted.value) return;
    playChime(
      [
        { freq: 880, at: 0 },
        { freq: 1175, at: 0.09 },
      ],
      0.25,
      0.2,
    );
  };

  return { playMessageSound, muted, toggleMuted };
};
