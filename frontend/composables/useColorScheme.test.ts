import { describe, it, expect, afterEach, vi } from 'vitest';
import { withSetup } from '../test/withSetup';
import { useColorScheme } from './useColorScheme';

const mockMatchMedia = (prefersDark: boolean) => {
  const listeners: Array<(e: MediaQueryListEvent) => void> = [];
  const mql = {
    matches: prefersDark,
    addEventListener: (_: string, cb: (e: MediaQueryListEvent) => void) => listeners.push(cb),
    removeEventListener: vi.fn(),
  };
  vi.stubGlobal('matchMedia', vi.fn().mockReturnValue(mql as unknown as MediaQueryList));

  return {
    fire: (matches: boolean) => listeners.forEach((cb) => cb({ matches } as MediaQueryListEvent)),
  };
};

describe('useColorScheme', () => {
  afterEach(() => {
    localStorage.clear();
    vi.unstubAllGlobals();
  });

  it('falls back to the given default when nothing stored and the OS has no preference', async () => {
    mockMatchMedia(false);

    const [{ scheme }, wrapper] = await withSetup(() => useColorScheme('light'));

    expect(scheme.value).toBe('light');
    wrapper.unmount();
  });

  it('resolves to dark when the OS prefers dark and nothing is stored', async () => {
    mockMatchMedia(true);

    const [{ scheme }, wrapper] = await withSetup(() => useColorScheme('light'));

    expect(scheme.value).toBe('dark');
    wrapper.unmount();
  });

  it("prefers the visitor's stored choice over the OS preference", async () => {
    mockMatchMedia(true);
    localStorage.setItem('chatbot:color_scheme', 'light');

    const [{ scheme }, wrapper] = await withSetup(() => useColorScheme('light'));

    expect(scheme.value).toBe('light');
    wrapper.unmount();
  });

  it('toggle() flips the scheme and persists the choice', async () => {
    mockMatchMedia(false);

    const [{ scheme, toggle }, wrapper] = await withSetup(() => useColorScheme('light'));
    expect(scheme.value).toBe('light');

    toggle();

    expect(scheme.value).toBe('dark');
    expect(localStorage.getItem('chatbot:color_scheme')).toBe('dark');
    wrapper.unmount();
  });

  it('stops following live OS-preference changes once the visitor has an explicit choice', async () => {
    const media = mockMatchMedia(false);

    const [{ scheme, toggle }, wrapper] = await withSetup(() => useColorScheme('light'));
    toggle(); // explicit choice: dark, persisted to localStorage
    expect(scheme.value).toBe('dark');

    // Same MediaQueryList the composable actually subscribed to in
    // onMounted -- firing its change listener simulates the OS reporting
    // "no longer prefers dark". The guard in onChange re-reads localStorage,
    // finds the explicit choice from toggle() above, and must not override it.
    media.fire(false);

    expect(scheme.value).toBe('dark');
    wrapper.unmount();
  });
});
