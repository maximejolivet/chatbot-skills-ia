import { describe, it, expect, afterEach } from 'vitest';
import { withSetup } from '../test/withSetup';
import { useOnlineStatus } from './useOnlineStatus';

describe('useOnlineStatus', () => {
  afterEach(() => {
    Object.defineProperty(navigator, 'onLine', { value: true, configurable: true });
  });

  it('reflects navigator.onLine at mount time', async () => {
    Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });

    const [isOnline, wrapper] = await withSetup(() => useOnlineStatus());

    expect(isOnline.value).toBe(false);
    wrapper.unmount();
  });

  it('flips to false when the browser goes offline', async () => {
    const [isOnline, wrapper] = await withSetup(() => useOnlineStatus());
    expect(isOnline.value).toBe(true);

    Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
    window.dispatchEvent(new Event('offline'));

    expect(isOnline.value).toBe(false);
    wrapper.unmount();
  });

  it('flips back to true when the browser comes back online', async () => {
    Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
    const [isOnline, wrapper] = await withSetup(() => useOnlineStatus());
    expect(isOnline.value).toBe(false);

    Object.defineProperty(navigator, 'onLine', { value: true, configurable: true });
    window.dispatchEvent(new Event('online'));

    expect(isOnline.value).toBe(true);
    wrapper.unmount();
  });

  it('stops reacting to connectivity events after unmount', async () => {
    const [isOnline, wrapper] = await withSetup(() => useOnlineStatus());
    wrapper.unmount();

    Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
    window.dispatchEvent(new Event('offline'));

    // The composable's own state should be frozen at whatever it was when
    // its listeners were torn down -- not asserting a stale `true` forever,
    // just that a post-unmount event no longer flips it.
    expect(isOnline.value).toBe(true);
  });
});
