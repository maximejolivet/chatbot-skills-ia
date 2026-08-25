import { describe, it, expect, vi } from 'vitest';
import { mockNuxtImport } from '@nuxt/test-utils/runtime';
import { useDebugMode } from './useDebugMode';

mockNuxtImport('useRoute', () => {
  return vi.fn(() => ({ query: {} }) as any);
});

describe('useDebugMode', () => {
  it('is false when the URL has no ?debug param', async () => {
    const { useRoute } = await import('#app');
    vi.mocked(useRoute).mockReturnValue({ query: {} } as any);

    expect(useDebugMode().value).toBe(false);
  });

  it('is false when ?debug is set to anything other than "1"', async () => {
    const { useRoute } = await import('#app');
    vi.mocked(useRoute).mockReturnValue({ query: { debug: 'true' } } as any);

    expect(useDebugMode().value).toBe(false);
  });

  it('is true when ?debug=1', async () => {
    const { useRoute } = await import('#app');
    vi.mocked(useRoute).mockReturnValue({ query: { debug: '1' } } as any);

    expect(useDebugMode().value).toBe(true);
  });
});
