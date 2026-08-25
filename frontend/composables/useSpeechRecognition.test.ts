import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { withSetup } from '../test/withSetup';
import { useSpeechRecognition } from './useSpeechRecognition';

// Web Speech API has no happy-dom/jsdom implementation -- a minimal fake
// constructor stands in for the browser global, capturing the config the
// composable sets (lang/interimResults/continuous) and letting tests fire
// its onresult/onend/onerror handlers by hand.
class FakeSpeechRecognition {
  lang = '';
  interimResults = false;
  continuous = true;
  onresult: ((event: unknown) => void) | null = null;
  onend: (() => void) | null = null;
  onerror: (() => void) | null = null;
  start = vi.fn();
  stop = vi.fn();
}

afterEach(() => {
  delete (window as any).SpeechRecognition;
  delete (window as any).webkitSpeechRecognition;
});

describe('useSpeechRecognition: unsupported browser', () => {
  it('reports unsupported and toggleListening is a no-op', async () => {
    const onTranscript = vi.fn();
    const [recognition, wrapper] = await withSetup(() => useSpeechRecognition(onTranscript));

    expect(recognition.isSupported).toBe(false);

    recognition.toggleListening();

    expect(recognition.isListening.value).toBe(false);
    expect(onTranscript).not.toHaveBeenCalled();
    wrapper.unmount();
  });
});

describe('useSpeechRecognition: supported browser', () => {
  beforeEach(() => {
    (window as any).SpeechRecognition = FakeSpeechRecognition;
  });

  it('reports supported via the unprefixed global', async () => {
    const [recognition, wrapper] = await withSetup(() => useSpeechRecognition(vi.fn()));
    expect(recognition.isSupported).toBe(true);
    wrapper.unmount();
  });

  it('reports supported via the webkit-prefixed global', async () => {
    delete (window as any).SpeechRecognition;
    (window as any).webkitSpeechRecognition = FakeSpeechRecognition;

    const [recognition, wrapper] = await withSetup(() => useSpeechRecognition(vi.fn()));
    expect(recognition.isSupported).toBe(true);
    wrapper.unmount();
  });

  it('stops (rather than restarts) when toggled again while listening', async () => {
    let created: FakeSpeechRecognition | null = null;
    class TrackedFakeSpeechRecognition extends FakeSpeechRecognition {
      constructor() {
        super();
        created = this;
      }
    }
    (window as any).SpeechRecognition = TrackedFakeSpeechRecognition;

    const [recognition, wrapper] = await withSetup(() => useSpeechRecognition(vi.fn()));

    recognition.toggleListening();
    expect(recognition.isListening.value).toBe(true);

    recognition.toggleListening();

    expect(created!.stop).toHaveBeenCalledTimes(1);
    expect(created!.start).toHaveBeenCalledTimes(1);
    wrapper.unmount();
  });

  it('joins interim + final results and forwards the transcript', async () => {
    let created: FakeSpeechRecognition | null = null;
    class TrackedFakeSpeechRecognition extends FakeSpeechRecognition {
      constructor() {
        super();
        created = this;
      }
    }
    (window as any).SpeechRecognition = TrackedFakeSpeechRecognition;

    const onTranscript = vi.fn();
    const [recognition, wrapper] = await withSetup(() => useSpeechRecognition(onTranscript));
    recognition.toggleListening();

    created!.onresult!({
      results: [[{ transcript: 'bonjour ' }], [{ transcript: 'le monde' }]],
    });

    expect(onTranscript).toHaveBeenCalledWith('bonjour le monde');
    expect(created!.lang).toBe('fr-FR');
    expect(created!.interimResults).toBe(true);
    expect(created!.continuous).toBe(false);
    wrapper.unmount();
  });

  it('stops listening when recognition ends or errors', async () => {
    let created: FakeSpeechRecognition | null = null;
    class TrackedFakeSpeechRecognition extends FakeSpeechRecognition {
      constructor() {
        super();
        created = this;
      }
    }
    (window as any).SpeechRecognition = TrackedFakeSpeechRecognition;

    const [recognition, wrapper] = await withSetup(() => useSpeechRecognition(vi.fn()));
    recognition.toggleListening();
    expect(recognition.isListening.value).toBe(true);

    created!.onend!();
    expect(recognition.isListening.value).toBe(false);

    recognition.toggleListening();
    expect(recognition.isListening.value).toBe(true);

    created!.onerror!();
    expect(recognition.isListening.value).toBe(false);
    wrapper.unmount();
  });

  it('stops an in-progress recognition on unmount', async () => {
    let created: FakeSpeechRecognition | null = null;
    class TrackedFakeSpeechRecognition extends FakeSpeechRecognition {
      constructor() {
        super();
        created = this;
      }
    }
    (window as any).SpeechRecognition = TrackedFakeSpeechRecognition;

    const [recognition, wrapper] = await withSetup(() => useSpeechRecognition(vi.fn()));
    recognition.toggleListening();

    wrapper.unmount();

    expect(created!.stop).toHaveBeenCalledTimes(1);
  });
});
