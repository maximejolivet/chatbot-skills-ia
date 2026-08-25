import { describe, it, expect, beforeEach, vi } from 'vitest';
import { registerEndpoint, mockNuxtImport } from '@nuxt/test-utils/runtime';
import { withSetup } from '../test/withSetup';
import {
  useChatbot,
  CONVERSATION_ID_STORAGE_KEY,
  LAST_MESSAGE_PREVIEW_KEY,
  PINNED_MESSAGES_KEY,
} from './useChatbot';
import type { ChatbotState } from '../types/index';

// Real WebAudio isn't available in the test environment (happy-dom); the
// notification chime is an unrelated concern to this composable's logic.
mockNuxtImport('useNotificationSound', () => {
  return () => ({ playMessageSound: vi.fn() });
});

// Always-empty by default so onMounted()'s fetchAgents()/checkLlmStatus()
// (which run for every useChatbot() call, not just the tests targeting
// them) have something to resolve instead of failing loudly in every test.
registerEndpoint('/api/ai_agents', () => ({ member: [] }));
registerEndpoint('/api/chat/llm-status', () => ({ status: 'not_running' }));

const realFetch = globalThis.fetch;

/**
 * sendMessage()'s streaming half talks to the browser's raw `fetch` (not
 * Nuxt's $fetch/ofetch -- see server/api/conversations/[id]/stream.post.ts),
 * so registerEndpoint (which mocks Nitro routes reached through ofetch)
 * doesn't cover it. Stubs global fetch for '/stream' URLs only; every other
 * URL (ensureConversation's POST /api/conversations, fetchAgents, ...)
 * still goes through the real fetch into the test Nitro server registerEndpoint
 * sets up, so both mocking styles coexist in the same test.
 */
function stubStreamFetch(
  frames: string[],
  { ok = true, status = 200 }: { ok?: boolean; status?: number } = {},
) {
  const encoder = new TextEncoder();
  let sent = false;

  vi.stubGlobal(
    'fetch',
    vi.fn((url: string, init?: RequestInit) => {
      if (typeof url !== 'string' || !url.includes('/stream')) {
        return realFetch(url, init as any);
      }

      return Promise.resolve({
        ok,
        status,
        body: {
          getReader: () => ({
            read: () => {
              if (sent) return Promise.resolve({ done: true, value: undefined });
              sent = true;

              return Promise.resolve({ done: false, value: encoder.encode(frames.join('')) });
            },
          }),
        },
      } as unknown as Response);
    }),
  );
}

function sseFrame(payload: Record<string, unknown>): string {
  return `data: ${JSON.stringify(payload)}\n\n`;
}

/**
 * Like stubStreamFetch, but splits the frames across two separate reader.read()
 * calls instead of delivering them all in one chunk -- needed to observe
 * toolCallLabel's intermediate value (see the "tool_call frames" describe
 * block below), which the buffered tool-calling path always clears again on
 * the very next (synchronous) frame in real usage, so a single-chunk stub
 * would never let a test see it set.
 */
function stubGatedToolCallStream(firstFrame: string, restFrames: string[]) {
  const encoder = new TextEncoder();
  let call = 0;
  let notifyReachedGate: () => void;
  const reachedGate = new Promise<void>((resolve) => {
    notifyReachedGate = resolve;
  });
  let releaseGate: () => void;
  const gate = new Promise<void>((resolve) => {
    releaseGate = resolve;
  });

  vi.stubGlobal(
    'fetch',
    vi.fn((url: string, init?: RequestInit) => {
      if (typeof url !== 'string' || !url.includes('/stream')) {
        return realFetch(url, init as any);
      }

      return Promise.resolve({
        ok: true,
        status: 200,
        body: {
          getReader: () => ({
            read: async () => {
              call += 1;
              if (1 === call) {
                return { done: false, value: encoder.encode(firstFrame) };
              }
              if (2 === call) {
                notifyReachedGate();
                await gate;

                return { done: false, value: encoder.encode(restFrames.join('')) };
              }

              return { done: true, value: undefined };
            },
          }),
        },
      } as unknown as Response);
    }),
  );

  return { reachedGate, releaseGate: () => releaseGate() };
}

beforeEach(() => {
  useState<ChatbotState>('chatbot-state', () => ({
    messages: [],
    isLoading: false,
    inputValue: '',
    error: null,
    selectedAgentId: null,
    agents: [],
  })).value = {
    messages: [],
    isLoading: false,
    inputValue: '',
    error: null,
    selectedAgentId: null,
    agents: [],
  };
  useState<number | null>('chatbot-conversation-id', () => null).value = null;
  useState<string | null>('chatbot-pending-message', () => null).value = null;
  localStorage.clear();
  vi.unstubAllGlobals();
  globalThis.fetch = realFetch;
});

describe('useChatbot: sendMessage guards', () => {
  it('does not send a blank/whitespace-only message', async () => {
    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('   ');

    expect(chatbot.messages.value).toEqual([]);
    wrapper.unmount();
  });

  it('does not send while already loading', async () => {
    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    // @ts-expect-error -- reaching into the shared state on purpose to
    // simulate "a send is already in flight" without racing a real one.
    useState<ChatbotState>('chatbot-state').value.isLoading = true;

    await chatbot.sendMessage('Hello');

    expect(chatbot.messages.value).toEqual([]);
    wrapper.unmount();
  });

  it('refuses to send while offline, with a clear error and no network call', async () => {
    // useOnlineStatus() reads navigator.onLine synchronously in its own
    // onMounted -- set it before mounting useChatbot() (which mounts
    // useOnlineStatus with it) rather than mocking the composable itself.
    Object.defineProperty(navigator, 'onLine', { value: false, configurable: true });
    const fetchSpy = vi.fn(realFetch);
    vi.stubGlobal('fetch', fetchSpy);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Hello');

    expect(chatbot.error.value).toMatch(/hors ligne/i);
    expect(fetchSpy).not.toHaveBeenCalled();
    // The optimistic user bubble is still pushed -- only the network call
    // is skipped -- so the visitor doesn't lose what they typed.
    expect(chatbot.messages.value).toHaveLength(1);
    expect(chatbot.messages.value[0].role).toBe('user');

    wrapper.unmount();
    Object.defineProperty(navigator, 'onLine', { value: true, configurable: true });
  });
});

describe('useChatbot: sendMessage happy path', () => {
  beforeEach(() => {
    registerEndpoint('/api/conversations', { method: 'POST', handler: () => ({ id: 42 }) });
  });

  it('appends the user message immediately, then the assistant reply from the stream', async () => {
    stubStreamFetch([
      sseFrame({ type: 'user_message' }),
      sseFrame({
        type: 'ai_complete',
        id: 7,
        content: 'Bonjour !',
        created_at: '2026-08-22T10:00:00+00:00',
        metadata: {
          sources: [{ document_id: 1, document_title: 'CV', score: 0.9 }],
          tool_calls: [],
          token_usage: {
            prompt_tokens: 10,
            completion_tokens: 5,
            total_tokens: 15,
            source: 'provider',
            provider: 'ollama',
            model: 'gpt-oss',
          },
        },
      }),
      sseFrame({ type: 'done' }),
    ]);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Salut');

    expect(chatbot.messages.value).toHaveLength(2);
    expect(chatbot.messages.value[0]).toMatchObject({ role: 'user', content: 'Salut' });
    expect(chatbot.messages.value[1]).toMatchObject({
      role: 'assistant',
      id: '7',
      content: 'Bonjour !',
    });
    expect(chatbot.messages.value[1].sources).toEqual([
      { document_id: 1, document_title: 'CV', score: 0.9 },
    ]);
    expect(chatbot.messages.value[1].tokenUsage?.total_tokens).toBe(15);
    expect(chatbot.isLoading.value).toBe(false);
    expect(chatbot.error.value).toBeNull();
    wrapper.unmount();
  });

  it('builds up the assistant bubble from delta frames instead of appending a duplicate at ai_complete', async () => {
    stubStreamFetch([
      sseFrame({ type: 'user_message' }),
      sseFrame({ type: 'delta', content: 'Bon' }),
      sseFrame({ type: 'delta', content: 'jour' }),
      sseFrame({ type: 'delta', content: ' !' }),
      sseFrame({
        type: 'ai_complete',
        id: 7,
        content: 'Bonjour !',
        created_at: '2026-08-22T10:00:00+00:00',
        metadata: {},
      }),
      sseFrame({ type: 'done' }),
    ]);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Salut');

    // Still exactly one assistant bubble -- the 3 deltas grew the same
    // message in place, ai_complete only filled in its final id/metadata.
    expect(chatbot.messages.value).toHaveLength(2);
    expect(chatbot.messages.value[1]).toMatchObject({
      role: 'assistant',
      id: '7',
      content: 'Bonjour !',
    });
    wrapper.unmount();
  });

  it('clears inputValue and sets an error when the stream reports an error frame', async () => {
    stubStreamFetch([sseFrame({ type: 'error', content: 'LLM unavailable' })]);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Salut');

    expect(chatbot.messages.value).toHaveLength(1); // only the optimistic user message
    expect(chatbot.error.value).not.toBeNull();
    expect(chatbot.isLoading.value).toBe(false);
    wrapper.unmount();
  });

  it('sets a generic error when the stream request itself fails (non-ok response)', async () => {
    stubStreamFetch([], { ok: false, status: 500 });

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Salut');

    expect(chatbot.error.value).toMatch(/erreur/i);
    expect(chatbot.isLoading.value).toBe(false);
    wrapper.unmount();
  });
});

describe('useChatbot: toolCallLabel', () => {
  beforeEach(() => {
    registerEndpoint('/api/conversations', { method: 'POST', handler: () => ({ id: 42 }) });
  });

  it('surfaces a friendly label for a known workflow tool, then clears it once content streams in', async () => {
    const { reachedGate, releaseGate } = stubGatedToolCallStream(
      sseFrame({ type: 'tool_call', tool: 'lister_creneaux_disponibles' }),
      [
        sseFrame({ type: 'delta', content: 'Voici les créneaux.' }),
        sseFrame({
          type: 'ai_complete',
          id: 9,
          content: 'Voici les créneaux.',
          created_at: '2026-08-24T10:00:00+00:00',
          metadata: {},
        }),
        sseFrame({ type: 'done' }),
      ],
    );

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    const sendPromise = chatbot.sendMessage('Quels créneaux sont disponibles ?');
    await reachedGate;

    expect(chatbot.toolCallLabel.value).toMatch(/disponibilit/i);

    releaseGate();
    await sendPromise;

    expect(chatbot.toolCallLabel.value).toBeNull();
    wrapper.unmount();
  });

  it('falls back to a generic label for an unrecognized tool name rather than leaking it raw', async () => {
    const { reachedGate, releaseGate } = stubGatedToolCallStream(
      sseFrame({ type: 'tool_call', tool: 'un_futur_workflow_inconnu' }),
      [
        sseFrame({ type: 'delta', content: 'Réponse.' }),
        sseFrame({
          type: 'ai_complete',
          id: 9,
          content: 'Réponse.',
          created_at: '2026-08-24T10:00:00+00:00',
          metadata: {},
        }),
        sseFrame({ type: 'done' }),
      ],
    );

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    const sendPromise = chatbot.sendMessage('Une question quelconque ?');
    await reachedGate;

    expect(chatbot.toolCallLabel.value).not.toBeNull();
    expect(chatbot.toolCallLabel.value).not.toMatch(/un_futur_workflow_inconnu/);

    releaseGate();
    await sendPromise;
    wrapper.unmount();
  });
});

describe('useChatbot: retryLastMessage', () => {
  beforeEach(() => {
    registerEndpoint('/api/conversations', { method: 'POST', handler: () => ({ id: 42 }) });
  });

  it('does nothing when there is no prior user message', async () => {
    const fetchSpy = vi.fn(realFetch);
    vi.stubGlobal('fetch', fetchSpy);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.retryLastMessage();

    expect(chatbot.messages.value).toEqual([]);
    wrapper.unmount();
  });

  it('resends the last user message without duplicating its bubble', async () => {
    stubStreamFetch([sseFrame({ type: 'error', content: 'first attempt fails' })]);
    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Une question');
    expect(chatbot.messages.value).toHaveLength(1);
    expect(chatbot.error.value).not.toBeNull();

    stubStreamFetch([
      sseFrame({
        type: 'ai_complete',
        id: 9,
        content: 'Réponse',
        created_at: '2026-08-22T10:00:00+00:00',
        metadata: {},
      }),
    ]);
    await chatbot.retryLastMessage();

    expect(chatbot.messages.value).toHaveLength(2);
    expect(chatbot.messages.value.filter((m) => m.role === 'user')).toHaveLength(1);
    expect(chatbot.messages.value[1]).toMatchObject({ role: 'assistant', content: 'Réponse' });
    expect(chatbot.error.value).toBeNull();
    wrapper.unmount();
  });
});

describe('useChatbot: regenerateLastReply', () => {
  beforeEach(() => {
    registerEndpoint('/api/conversations', { method: 'POST', handler: () => ({ id: 42 }) });
  });

  it('is a no-op when the last message is not an assistant reply', async () => {
    const fetchSpy = vi.fn(realFetch);
    vi.stubGlobal('fetch', fetchSpy);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.regenerateLastReply();

    expect(fetchSpy).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('replaces the last assistant reply instead of appending a duplicate', async () => {
    stubStreamFetch([
      sseFrame({
        type: 'ai_complete',
        id: 1,
        content: 'Première réponse',
        created_at: '2026-08-22T10:00:00+00:00',
        metadata: {},
      }),
    ]);
    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Une question');
    expect(chatbot.messages.value).toHaveLength(2);

    stubStreamFetch([
      sseFrame({
        type: 'ai_complete',
        id: 2,
        content: 'Seconde réponse',
        created_at: '2026-08-22T10:01:00+00:00',
        metadata: {},
      }),
    ]);
    await chatbot.regenerateLastReply();

    expect(chatbot.messages.value).toHaveLength(2);
    expect(chatbot.messages.value.filter((m) => m.role === 'user')).toHaveLength(1);
    expect(chatbot.messages.value[1]).toMatchObject({
      role: 'assistant',
      content: 'Seconde réponse',
    });
    wrapper.unmount();
  });
});

describe('useChatbot: cancelReply', () => {
  beforeEach(() => {
    registerEndpoint('/api/conversations', { method: 'POST', handler: () => ({ id: 42 }) });
  });

  it('aborts an in-flight request without setting an error', async () => {
    let notifyFetchCalled: () => void;
    const fetchCalled = new Promise<void>((resolve) => {
      notifyFetchCalled = resolve;
    });

    vi.stubGlobal(
      'fetch',
      vi.fn((url: string, init?: RequestInit) => {
        if (typeof url !== 'string' || !url.includes('/stream')) {
          return realFetch(url, init as any);
        }

        notifyFetchCalled();

        // Mirrors the real browser fetch()/AbortController contract: never
        // resolves on its own, only rejects once the signal fires.
        return new Promise((_resolve, reject) => {
          init?.signal?.addEventListener('abort', () => {
            reject(new DOMException('Aborted', 'AbortError'));
          });
        });
      }),
    );

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    const sendPromise = chatbot.sendMessage('Salut');
    await fetchCalled;

    expect(chatbot.isLoading.value).toBe(true);
    chatbot.cancelReply();
    await sendPromise;

    expect(chatbot.isLoading.value).toBe(false);
    expect(chatbot.error.value).toBeNull();
    // No assistant bubble either -- the abort happened before any delta
    // frame ever streamed in, only the optimistic user message remains.
    expect(chatbot.messages.value).toHaveLength(1);
    wrapper.unmount();
  });
});

describe('useChatbot: autoScroll gating', () => {
  it('scrollToBottom is a no-op while autoScroll is disabled', async () => {
    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    const scrollIntoView = vi.fn();
    // @ts-expect-error -- minimal stub, scrollToBottom only ever calls this
    chatbot.messagesEndRef.value = { scrollIntoView };

    chatbot.autoScroll.value = false;
    await chatbot.scrollToBottom();
    expect(scrollIntoView).not.toHaveBeenCalled();

    chatbot.autoScroll.value = true;
    await chatbot.scrollToBottom();
    expect(scrollIntoView).toHaveBeenCalledTimes(1);

    wrapper.unmount();
  });

  it('sendMessage re-enables autoScroll -- sending is an explicit "back to the live edge" action', async () => {
    registerEndpoint('/api/conversations', { method: 'POST', handler: () => ({ id: 42 }) });
    stubStreamFetch([
      sseFrame({
        type: 'ai_complete',
        id: 1,
        content: 'Réponse',
        created_at: '2026-08-22T10:00:00+00:00',
        metadata: {},
      }),
    ]);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    chatbot.autoScroll.value = false;

    await chatbot.sendMessage('Salut');

    expect(chatbot.autoScroll.value).toBe(true);
    wrapper.unmount();
  });
});

describe('useChatbot: desktop notifications', () => {
  beforeEach(() => {
    registerEndpoint('/api/conversations', { method: 'POST', handler: () => ({ id: 42 }) });
  });

  const stubNotification = (permission: NotificationPermission) => {
    const instances: Array<{ title: string; options: NotificationOptions }> = [];
    class FakeNotification {
      static permission = permission;
      static requestPermission = () => Promise.resolve(permission);
      onclick: (() => void) | null = null;
      constructor(title: string, options: NotificationOptions = {}) {
        instances.push({ title, options });
      }
      close() {}
    }
    vi.stubGlobal('Notification', FakeNotification);

    return instances;
  };

  it('notifies when the tab is hidden and permission is already granted', async () => {
    const instances = stubNotification('granted');
    Object.defineProperty(document, 'hidden', { value: true, configurable: true });
    stubStreamFetch([
      sseFrame({
        type: 'ai_complete',
        id: 1,
        content: 'Réponse pendant que l’onglet est en arrière-plan',
        created_at: '2026-08-22T10:00:00+00:00',
        metadata: {},
      }),
    ]);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Salut');

    expect(instances).toHaveLength(1);
    expect(instances[0].options.body).toContain('Réponse pendant que l’onglet est en arrière-plan');

    Object.defineProperty(document, 'hidden', { value: false, configurable: true });
    wrapper.unmount();
  });

  it('does not notify while the tab is visible', async () => {
    const instances = stubNotification('granted');
    Object.defineProperty(document, 'hidden', { value: false, configurable: true });
    stubStreamFetch([
      sseFrame({
        type: 'ai_complete',
        id: 1,
        content: 'Réponse',
        created_at: '2026-08-22T10:00:00+00:00',
        metadata: {},
      }),
    ]);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Salut');

    expect(instances).toHaveLength(0);
    wrapper.unmount();
  });

  it('does not notify without granted permission', async () => {
    const instances = stubNotification('default');
    Object.defineProperty(document, 'hidden', { value: true, configurable: true });
    stubStreamFetch([
      sseFrame({
        type: 'ai_complete',
        id: 1,
        content: 'Réponse',
        created_at: '2026-08-22T10:00:00+00:00',
        metadata: {},
      }),
    ]);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Salut');

    expect(instances).toHaveLength(0);

    Object.defineProperty(document, 'hidden', { value: false, configurable: true });
    wrapper.unmount();
  });
});

describe('useChatbot: setFeedback', () => {
  const seedAssistantMessage = () => {
    localStorage.setItem(CONVERSATION_ID_STORAGE_KEY, '123');
    useState<number | null>('chatbot-conversation-id').value = 123;
    useState<ChatbotState>('chatbot-state').value.messages = [
      { id: 'msg-1', content: 'Réponse', role: 'assistant', timestamp: new Date(), feedback: null },
    ];
  };

  it('applies the choice optimistically and persists it', async () => {
    seedAssistantMessage();
    registerEndpoint('/api/conversations/123/messages/msg-1/feedback', {
      method: 'PATCH',
      handler: () => ({ id: 9, feedback: 'positive' }),
    });

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.setFeedback('msg-1', 'positive');

    expect(chatbot.messages.value[0].feedback).toBe('positive');
    wrapper.unmount();
  });

  it('toggles back to null when the same choice is clicked again', async () => {
    seedAssistantMessage();
    registerEndpoint('/api/conversations/123/messages/msg-1/feedback', {
      method: 'PATCH',
      handler: () => ({ id: 9, feedback: null }),
    });

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.setFeedback('msg-1', null);

    expect(chatbot.messages.value[0].feedback).toBeNull();
    wrapper.unmount();
  });

  it('rolls back the optimistic update if the request fails', async () => {
    seedAssistantMessage();
    registerEndpoint('/api/conversations/123/messages/msg-1/feedback', {
      method: 'PATCH',
      handler: () => {
        throw new Error('boom');
      },
    });

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.setFeedback('msg-1', 'negative');

    expect(chatbot.messages.value[0].feedback).toBeNull();
    wrapper.unmount();
  });

  it('is a no-op without a persisted conversation', async () => {
    useState<number | null>('chatbot-conversation-id').value = null;
    useState<ChatbotState>('chatbot-state').value.messages = [
      { id: 'msg-1', content: 'Réponse', role: 'assistant', timestamp: new Date(), feedback: null },
    ];

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.setFeedback('msg-1', 'positive');

    expect(chatbot.messages.value[0].feedback).toBeNull();
    wrapper.unmount();
  });
});

describe('useChatbot: togglePin', () => {
  afterEach(() => {
    localStorage.removeItem(PINNED_MESSAGES_KEY);
  });

  it('pins and unpins a message, exposing it via pinnedMessages', async () => {
    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    useState<ChatbotState>('chatbot-state').value.messages = [
      { id: 'msg-1', content: 'Réponse', role: 'assistant', timestamp: new Date() },
    ];

    chatbot.togglePin('msg-1');
    expect(chatbot.messages.value[0].pinned).toBe(true);
    expect(chatbot.pinnedMessages.value.map((m) => m.id)).toEqual(['msg-1']);
    expect(JSON.parse(localStorage.getItem(PINNED_MESSAGES_KEY) ?? '[]')).toEqual(['msg-1']);

    chatbot.togglePin('msg-1');
    expect(chatbot.messages.value[0].pinned).toBe(false);
    expect(chatbot.pinnedMessages.value).toEqual([]);
    expect(JSON.parse(localStorage.getItem(PINNED_MESSAGES_KEY) ?? '[]')).toEqual([]);

    wrapper.unmount();
  });

  it('is a no-op for an unknown message id', async () => {
    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    useState<ChatbotState>('chatbot-state').value.messages = [
      { id: 'msg-1', content: 'Réponse', role: 'assistant', timestamp: new Date() },
    ];

    chatbot.togglePin('does-not-exist');

    expect(chatbot.pinnedMessages.value).toEqual([]);
    wrapper.unmount();
  });

  it('re-applies pinned status when a conversation is restored', async () => {
    localStorage.setItem(CONVERSATION_ID_STORAGE_KEY, '55');
    localStorage.setItem(PINNED_MESSAGES_KEY, JSON.stringify(['9']));
    registerEndpoint('/api/conversations/55/messages', () => [
      { id: 9, role: 'assistant', content: 'Important', created_at: '2026-08-24T10:00:00+00:00' },
      { id: 10, role: 'user', content: 'Autre chose', created_at: '2026-08-24T10:00:05+00:00' },
    ]);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await vi.waitFor(() => expect(chatbot.messages.value).toHaveLength(2));

    expect(chatbot.pinnedMessages.value.map((m) => m.id)).toEqual(['9']);
    wrapper.unmount();
  });
});

describe('useChatbot: clearMessages', () => {
  it('resets messages/error/conversationId and removes the persisted conversation id', async () => {
    localStorage.setItem(CONVERSATION_ID_STORAGE_KEY, '123');
    localStorage.setItem(LAST_MESSAGE_PREVIEW_KEY, 'Bonjour !');
    useState<number | null>('chatbot-conversation-id').value = 123;

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    useState<ChatbotState>('chatbot-state').value.messages = [
      { id: '1', content: 'hi', role: 'user', timestamp: new Date() },
    ];
    useState<ChatbotState>('chatbot-state').value.error = 'some error';

    chatbot.clearMessages();

    expect(chatbot.messages.value).toEqual([]);
    expect(chatbot.error.value).toBeNull();
    expect(localStorage.getItem(CONVERSATION_ID_STORAGE_KEY)).toBeNull();
    expect(localStorage.getItem(LAST_MESSAGE_PREVIEW_KEY)).toBeNull();
    wrapper.unmount();
  });
});

describe('useChatbot: last message preview persistence', () => {
  beforeEach(() => {
    registerEndpoint('/api/conversations', { method: 'POST', handler: () => ({ id: 42 }) });
  });

  it('stores the assistant reply for StickyChatBubble.vue to read on its next mount', async () => {
    stubStreamFetch([
      sseFrame({ type: 'user_message' }),
      sseFrame({
        type: 'ai_complete',
        id: 7,
        content: 'Bonjour, comment puis-je vous aider ?',
        created_at: '2026-08-22T10:00:00+00:00',
        metadata: {},
      }),
      sseFrame({ type: 'done' }),
    ]);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Salut');

    expect(localStorage.getItem(LAST_MESSAGE_PREVIEW_KEY)).toBe(
      'Bonjour, comment puis-je vous aider ?',
    );
    wrapper.unmount();
  });

  it('truncates a long reply', async () => {
    const longReply = 'A'.repeat(200);
    stubStreamFetch([
      sseFrame({ type: 'user_message' }),
      sseFrame({
        type: 'ai_complete',
        id: 7,
        content: longReply,
        created_at: '2026-08-22T10:00:00+00:00',
        metadata: {},
      }),
      sseFrame({ type: 'done' }),
    ]);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    await chatbot.sendMessage('Salut');

    const stored = localStorage.getItem(LAST_MESSAGE_PREVIEW_KEY);
    expect(stored).toHaveLength(121);
    expect(stored?.endsWith('…')).toBe(true);
    wrapper.unmount();
  });
});

describe('useChatbot: exportConversation', () => {
  // Node's real URL.createObjectURL (happy-dom doesn't provide its own)
  // validates its argument against Node's own Blob class via `instanceof`,
  // which the app code's globalThis.Blob doesn't necessarily resolve to in
  // this environment ("Received an instance of Blob" despite it being a real
  // Blob) -- mocked outright instead of relying on the real implementation,
  // since the goal here is only to observe that a download was triggered,
  // not to exercise a real Blob URL.
  it('does nothing when the thread is empty', async () => {
    const createObjectURL = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:mock');
    const [chatbot, wrapper] = await withSetup(() => useChatbot());

    chatbot.exportConversation();

    expect(createObjectURL).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('builds a Markdown file from the thread and triggers a download', async () => {
    const createObjectURL = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:mock');
    const revokeObjectURL = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    useState<ChatbotState>('chatbot-state').value.messages = [
      { id: '1', content: 'Salut', role: 'user', timestamp: new Date('2026-01-01T10:00:00') },
      {
        id: '2',
        content: 'Bonjour !',
        role: 'assistant',
        timestamp: new Date('2026-01-01T10:00:05'),
      },
    ];

    chatbot.exportConversation();

    expect(createObjectURL).toHaveBeenCalledTimes(1);
    const blob = createObjectURL.mock.calls[0][0] as Blob;
    const text = await blob.text();
    expect(text).toContain('Toi');
    expect(text).toContain('Salut');
    expect(text).toContain('Assistant');
    expect(text).toContain('Bonjour !');
    expect(click).toHaveBeenCalledTimes(1);
    expect(revokeObjectURL).toHaveBeenCalledTimes(1);

    wrapper.unmount();
  });
});

describe('useChatbot: openCV', () => {
  it('opens the real, live CV URL in a new tab', async () => {
    const open = vi.spyOn(window, 'open').mockImplementation(() => null);

    const [chatbot, wrapper] = await withSetup(() => useChatbot());
    chatbot.openCV();

    expect(open).toHaveBeenCalledWith(
      'https://www.maxime.bzh/cv-maximejolivet-developpeur-web-fullstack-senior-lead-dev-tech-lead-ia.pdf',
      '_blank',
      'noopener',
    );

    wrapper.unmount();
  });
});
