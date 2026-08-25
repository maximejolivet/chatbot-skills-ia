import { describe, it, expect, afterEach } from 'vitest';
import { withSetup } from '../test/withSetup';
import { useNotificationSound } from './useNotificationSound';

describe('useNotificationSound', () => {
  afterEach(() => {
    localStorage.clear();
  });

  it('starts unmuted by default', async () => {
    const [{ muted }, wrapper] = await withSetup(() => useNotificationSound());

    expect(muted.value).toBe(false);
    wrapper.unmount();
  });

  it('reads a previously persisted mute choice on mount', async () => {
    localStorage.setItem('chatbot:sound_muted', 'true');

    const [{ muted }, wrapper] = await withSetup(() => useNotificationSound());

    expect(muted.value).toBe(true);
    wrapper.unmount();
  });

  it('toggleMuted() flips the flag and persists it', async () => {
    const [{ muted, toggleMuted }, wrapper] = await withSetup(() => useNotificationSound());
    expect(muted.value).toBe(false);

    toggleMuted();

    expect(muted.value).toBe(true);
    expect(localStorage.getItem('chatbot:sound_muted')).toBe('true');

    toggleMuted();

    expect(muted.value).toBe(false);
    expect(localStorage.getItem('chatbot:sound_muted')).toBe('false');
    wrapper.unmount();
  });

  it('playMessageSound() does not throw while muted (chime skipped)', async () => {
    const [{ muted, toggleMuted, playMessageSound }, wrapper] = await withSetup(() =>
      useNotificationSound(),
    );
    toggleMuted();
    expect(muted.value).toBe(true);

    expect(() => playMessageSound()).not.toThrow();
    wrapper.unmount();
  });
});
