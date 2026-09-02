import { ref, computed, nextTick, onMounted } from 'vue';
import type {
  Message,
  MessageSource,
  ToolCallTrace,
  TokenUsage,
  ChatbotState,
  AIAgent,
} from '../types/index';

interface UseChatbotProps {
  apiUrl?: string;
  onMessage?: (message: Message) => void;
}

// Keeps the conversation alive across page reloads/navigation within the
// same browser -- otherwise a visitor who refreshes mid-chat (or who already
// gave their name/email while booking an interview) loses everything and
// starts over.
export const CONVERSATION_ID_STORAGE_KEY = 'chatbot:conversation_id';

// Lets StickyChatBubble.vue show a teaser of the last assistant reply on
// hover *before* the widget/page has ever mounted useChatbot() itself (the
// bubble only opens the popin, or redirects to /chat, on click -- it never
// restores the full conversation just to render a preview). Plain
// localStorage rather than a shared useState: the bubble and the chat
// panel are never both interested in this at the same time.
export const LAST_MESSAGE_PREVIEW_KEY = 'chatbot:last_message_preview';
const PREVIEW_MAX_LENGTH = 120;

const persistLastMessagePreview = (message: Message) => {
  localStorage.setItem(
    LAST_MESSAGE_PREVIEW_KEY,
    message.content.length > PREVIEW_MAX_LENGTH
      ? `${message.content.slice(0, PREVIEW_MAX_LENGTH)}…`
      : message.content,
  );
};

// Pinning is purely a client-side organizational aid (no backend field for
// it) -- persisted as a bare set of message ids rather than on ChatbotState
// so it survives independently of the in-memory thread and can be applied
// back onto messages restored from the server (see restoreConversation).
export const PINNED_MESSAGES_KEY = 'chatbot:pinned_messages';

const readPinnedIds = (): Set<string> => {
  try {
    const raw = localStorage.getItem(PINNED_MESSAGES_KEY);
    return raw ? new Set(JSON.parse(raw)) : new Set();
  } catch {
    return new Set();
  }
};

const writePinnedIds = (ids: Set<string>) => {
  localStorage.setItem(PINNED_MESSAGES_KEY, JSON.stringify([...ids]));
};

export const useChatbot = ({ apiUrl = '/api/chat', onMessage }: UseChatbotProps = {}) => {
  const {
    playMessageSound,
    muted: soundMuted,
    toggleMuted: toggleSoundMuted,
  } = useNotificationSound();
  const { t } = useI18n();

  // useState (not ref): keeps the conversation in memory if the visitor
  // navigates away from /chat and back -- Chatbot.vue remounts on each
  // visit, so a plain ref would otherwise reset the whole thread.
  const state = useState<ChatbotState>('chatbot-state', () => ({
    messages: [],
    isLoading: false,
    inputValue: '',
    error: null,
    selectedAgentId: null,
    agents: [],
  }));

  // Lazily created on the first sent message -- a real, persisted
  // Conversation so message history is kept server-side. Restored from
  // localStorage on mount if a previous session left one behind. Shared
  // for the same reason as `state` above -- otherwise navigating away from
  // /chat mid-send and back would spin up a second, independent
  // conversation instead of continuing the first.
  const conversationId = useState<number | null>('chatbot-conversation-id', () => null);

  // Set by HeroChatBar.vue before navigating to /chat, so a question typed
  // on the landing page (before Chatbot.vue even exists in the DOM) still
  // gets sent once the chat page mounts, instead of being silently dropped.
  const pendingMessage = useState<string | null>('chatbot-pending-message', () => null);

  const messagesEndRef = ref<HTMLDivElement>();
  const isOnline = useOnlineStatus();

  // Chatbot.vue owns the actual scroll container and flips this off while
  // the visitor has scrolled up to read history, so an incoming reply
  // doesn't yank them back down -- it shows a "new message" pill instead
  // (see Chatbot.vue's own scroll handler). Forced back to true whenever
  // the visitor takes an action that implies "take me to the live edge"
  // (send/retry/regenerate, see requestAssistantReply below).
  const autoScroll = ref(true);

  // Not shared via useState, same reasoning as llmStatus below: only ever
  // relevant while this composable instance is live and a request is in
  // flight, never something to persist or restore.
  let activeRequest: AbortController | null = null;

  // Not shared via useState: Chatbot.vue only mounts while the panel is
  // open, so a fresh check on every open is exactly the desired behavior
  // (no point caching connectivity across closes).
  const llmStatus = ref<'checking' | 'online' | 'offline'>('checking');

  // Drives a skeleton placeholder (Chatbot.vue) while a previous
  // conversation is being fetched back from localStorage's id -- without
  // it the panel flashes empty, then suddenly fills, on every reload of a
  // conversation with history.
  const isRestoringHistory = ref(false);

  // Set from the `tool_call` SSE frame (ConversationStreamController),
  // emitted right before a resolved workflow executes on the buffered
  // tool-calling path -- see ChatOrchestrationService's $onToolCall. That
  // whole path (LLM call -> tool execution -> second LLM call) happens
  // before any `delta` frame arrives, so this is only ever meaningful during
  // the same window TypingIndicator is already shown (no assistant message
  // in the thread yet); cleared the moment a delta lands. Holds the raw
  // internal tool name -- never rendered as-is (same "curated, not raw"
  // reasoning as the interview-confirmed card in MessageBubble.vue), see
  // toolCallLabel below for the known-name -> friendly-label mapping.
  const activeToolCall = ref<string | null>(null);

  // Only a few workflows exist today and each needs a French phrase that
  // makes sense mid-sentence ("... en cours") -- an unrecognized name (a
  // future workflow, or one whose exact string changes) falls back to a
  // generic label rather than ever leaking the raw internal name.
  const TOOL_CALL_LABEL_KEYS: Record<string, string> = {
    planifier_entretien: 'chatbot.toolCallPlanifierEntretien',
    lister_creneaux_disponibles: 'chatbot.toolCallListerCreneaux',
  };

  const toolCallLabel = computed(() => {
    if (!activeToolCall.value) return null;

    return t(TOOL_CALL_LABEL_KEYS[activeToolCall.value] ?? 'chatbot.toolCallGeneric');
  });

  const checkLlmStatus = async () => {
    llmStatus.value = 'checking';
    try {
      const response = await $fetch<{ status: string }>('/api/chat/llm-status');
      llmStatus.value = ['running', 'reachable'].includes(response.status) ? 'online' : 'offline';
    } catch (error) {
      console.error('Erreur lors de la vérification du statut LLM:', error);
      llmStatus.value = 'offline';
    }
  };

  const scrollToBottom = async () => {
    if (!autoScroll.value) return;
    await nextTick();
    // NOT `scrollIntoView({ behavior: 'smooth' })` -- confirmed (isolated
    // repro, unrelated to this app's own CSS) a real Chromium bug: smooth
    // scrollIntoView silently no-ops when its target sits inside a
    // scrollable container that is itself nested in an `overflow: hidden`
    // ancestor, which is exactly this panel's layout (page variant). The
    // conversation would visibly stay pinned at the top forever, never
    // following new messages down. `instant` on the same call is
    // unaffected by that bug and was verified to reliably reach the
    // bottom.
    messagesEndRef.value?.scrollIntoView({ behavior: 'instant', block: 'end' });
  };

  // Desktop notification when a reply arrives while the tab is in the
  // background -- complements playMessageSound() for a visitor who's
  // switched tabs and also has the chime muted. Permission is only ever
  // requested once per mount (see sendMessage), and only from inside a
  // user gesture (send), which is what most browsers require anyway.
  let notificationPermissionRequested = false;

  const ensureNotificationPermission = () => {
    if (notificationPermissionRequested) return;
    notificationPermissionRequested = true;

    if ('undefined' === typeof Notification || 'default' !== Notification.permission) return;
    Notification.requestPermission();
  };

  const notifyIfHidden = (message: Message) => {
    if ('undefined' === typeof Notification || 'granted' !== Notification.permission) return;
    if ('undefined' === typeof document || !document.hidden) return;

    const notification = new Notification(t('chatbot.defaultTitle'), {
      body: message.content.slice(0, 120),
    });
    notification.onclick = () => {
      window.focus();
      notification.close();
    };
  };

  const fetchAgents = async () => {
    try {
      // Utiliser une URL relative pour que Nuxt route vers le serveur API route
      // Le serveur API route proxy vers http://chatbot-symfony:8000 (nom de conteneur Docker)
      // API Platform renvoie une collection JSON-LD ({ member: [...] }), pas un tableau brut,
      // et sérialise isActive() en "active" (convention Symfony pour les getters is*/has*).
      // Regular API Platform CRUD operation (see ensureConversation below):
      // it only accepts application/ld+json. Without this header the Nuxt
      // proxy defaults to Accept: application/json, Symfony 406s, the error
      // is swallowed by the catch below, and selectedAgentId silently stays
      // null -- the RAG context then loses the agent's dedicated collection.
      const response = await $fetch<{
        member: Array<Omit<AIAgent, 'is_active'> & { active: boolean }>;
      }>('/api/ai_agents', {
        method: 'GET',
        credentials: 'include',
        headers: { 'Content-Type': 'application/ld+json' },
      });
      state.value.agents = (response?.member ?? []).map(({ active, ...agent }) => ({
        ...agent,
        is_active: active,
      }));

      const activeAgent = state.value.agents.find((agent) => agent.is_active);
      state.value.selectedAgentId = activeAgent?.id ?? null;
    } catch (error) {
      console.error('Erreur lors de la récupération des agents:', error);
    }
  };

  const ensureConversation = async (firstMessage: string): Promise<number> => {
    if (conversationId.value) return conversationId.value;

    // Unlike the custom controllers below (quick-send, messages, feedback --
    // deserialize: false, parse the raw body themselves), this is a regular
    // API Platform CRUD operation: it only accepts application/ld+json.
    const response = await $fetch<{ id: number }>('/api/conversations', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/ld+json' },
      body: { title: firstMessage.slice(0, 60), is_active: true },
    });
    conversationId.value = response.id;
    localStorage.setItem(CONVERSATION_ID_STORAGE_KEY, String(response.id));

    return response.id;
  };

  // Runs once on mount: if a previous session left a conversation id behind,
  // pick the thread back up (agent, visitor identity, and history all carry
  // over server-side) instead of silently starting a new one.
  const restoreConversation = async () => {
    const storedId = localStorage.getItem(CONVERSATION_ID_STORAGE_KEY);
    if (!storedId) return;

    isRestoringHistory.value = true;
    try {
      const response = await $fetch<
        Array<{
          id: number;
          role: 'user' | 'assistant';
          content: string;
          created_at: string;
          metadata?: {
            sources?: MessageSource[];
            tool_calls?: ToolCallTrace[];
            token_usage?: TokenUsage;
          };
          feedback?: 'positive' | 'negative' | null;
        }>
      >(`/api/conversations/${storedId}/messages`, {
        method: 'GET',
        credentials: 'include',
      });

      conversationId.value = Number(storedId);
      const pinnedIds = readPinnedIds();
      state.value.messages = response.map((message) => ({
        id: String(message.id),
        content: message.content,
        role: message.role,
        timestamp: new Date(message.created_at),
        sources: message.metadata?.sources,
        toolCalls: message.metadata?.tool_calls,
        tokenUsage: message.metadata?.token_usage,
        feedback: message.feedback ?? null,
        pinned: pinnedIds.has(String(message.id)),
      }));
      // Before scrollToBottom(), not after: it awaits nextTick() and
      // scrolls the DOM as it exists at that point -- if isRestoringHistory
      // were still true here, that DOM is still the short skeleton
      // placeholder (see the template's v-if), not the real (much taller)
      // message list. Scrolling "to the bottom" of the skeleton lands at
      // the top of the real content once it swaps in right after, with
      // nothing left to correct it.
      isRestoringHistory.value = false;
      await scrollToBottom();
    } catch (error) {
      // Conversation may have been deleted server-side (admin cleanup) --
      // drop the stale id and let the next message start a fresh one.
      console.error('Impossible de restaurer la conversation précédente:', error);
      localStorage.removeItem(CONVERSATION_ID_STORAGE_KEY);
    } finally {
      isRestoringHistory.value = false;
    }
  };

  const sendMessage = async (content: string) => {
    if (!content.trim() || state.value.isLoading) return;

    ensureNotificationPermission();

    const trimmed = content.trim();
    const userMessage: Message = {
      id: Date.now().toString(),
      content: trimmed,
      role: 'user',
      timestamp: new Date(),
    };

    state.value.messages.push(userMessage);
    state.value.inputValue = '';
    await requestAssistantReply(trimmed);
  };

  // Re-runs the request/response half of sendMessage() for the last user
  // message, without pushing a second copy of it into the thread -- wired to
  // a "Réessayer" button next to `error` in Chatbot.vue, so a failed send
  // (network blip, momentarily offline) doesn't force retyping the message.
  const retryLastMessage = async () => {
    if (state.value.isLoading) return;
    const lastUserMessage = [...state.value.messages].reverse().find((m) => m.role === 'user');
    if (!lastUserMessage) return;

    await requestAssistantReply(lastUserMessage.content);
  };

  // Re-rolls an existing assistant reply (as opposed to retryLastMessage,
  // which only ever runs after a *failed* attempt that never got as far as
  // pushing an assistant message). Only valid when the thread's last message
  // is that reply -- matches the "regenerate" button only being shown on the
  // last bubble in Chatbot.vue. Drops it before re-requesting so the new one
  // replaces it instead of appending a duplicate; the old reply still exists
  // server-side (a new Message row), just no longer shown client-side.
  const regenerateLastReply = async () => {
    if (state.value.isLoading) return;
    const messages = state.value.messages;
    const last = messages[messages.length - 1];
    if (!last || 'assistant' !== last.role) return;

    const lastUserMessage = [...messages].reverse().find((m) => 'user' === m.role);
    if (!lastUserMessage) return;

    messages.pop();
    await requestAssistantReply(lastUserMessage.content);
  };

  const requestAssistantReply = async (trimmedContent: string) => {
    if (!isOnline.value) {
      state.value.error = t('errors.offline');

      return;
    }

    state.value.isLoading = true;
    state.value.error = null;
    activeToolCall.value = null;
    // Sending/retrying/regenerating is an explicit "take me to the live
    // edge" action, regardless of where the visitor had scrolled to.
    autoScroll.value = true;

    await scrollToBottom();

    activeRequest = new AbortController();

    try {
      const convId = await ensureConversation(trimmedContent);

      const body: Record<string, unknown> = {
        message: trimmedContent,
      };

      if (state.value.selectedAgentId) {
        body.agent_id = state.value.selectedAgentId;
      }

      const response = await fetch(`/api/conversations/${convId}/stream`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
        signal: activeRequest.signal,
      });

      if (!response.ok || !response.body) {
        throw new Error(`Stream request failed with status ${response.status}`);
      }

      // ConversationStreamController emits newline-delimited SSE frames
      // ("data: {...}\n\n"): user_message (ignored, already pushed
      // optimistically above), zero or more delta frames (real token-level
      // chunks -- ChatOrchestrationService streams whenever the agent has no
      // active tools, and still emits exactly one delta with the full
      // content on the buffered/tool-calling path, so this loop never has to
      // know which one happened server-side), then ai_complete (the full
      // serialized Message -- read here only for metadata: id, sources,
      // tool_calls, feedback, token usage; its `content` is used only as a
      // fallback if no delta ever arrived).
      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = '';
      let assistantPayload: any = null;
      let streamError: string | null = null;
      let liveMessage: Message | null = null;

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });
        const frames = buffer.split('\n\n');
        buffer = frames.pop() ?? '';

        for (const frame of frames) {
          const line = frame.trim();
          if (!line.startsWith('data:')) continue;

          const payload = JSON.parse(line.slice('data:'.length).trim());

          if (payload.type === 'tool_call') activeToolCall.value = payload.tool ?? null;

          if (payload.type === 'delta') {
            activeToolCall.value = null;
            if (!liveMessage) {
              state.value.messages.push({
                id: `streaming-${Date.now()}`,
                content: '',
                role: 'assistant',
                timestamp: new Date(),
              });
              // Re-read through the reactive array rather than keeping the
              // plain object literal above -- Vue only makes the *proxy* it
              // wraps around a pushed object reactive, not that original
              // reference, so mutating the literal directly wouldn't trigger
              // re-renders.
              liveMessage = state.value.messages[state.value.messages.length - 1];
              await scrollToBottom();
            }
            liveMessage.content += payload.content;
          }
          if (payload.type === 'ai_complete') assistantPayload = payload;
          if (payload.type === 'error') streamError = payload.content;
        }
      }

      if (streamError) throw new Error(streamError);

      if (liveMessage) {
        liveMessage.id = String(assistantPayload?.id ?? liveMessage.id);
        liveMessage.content = assistantPayload?.content || liveMessage.content;
        liveMessage.timestamp = assistantPayload?.created_at
          ? new Date(assistantPayload.created_at)
          : liveMessage.timestamp;
        liveMessage.sources = assistantPayload?.metadata?.sources;
        liveMessage.toolCalls = assistantPayload?.metadata?.tool_calls;
        liveMessage.tokenUsage = assistantPayload?.metadata?.token_usage;
        liveMessage.feedback = assistantPayload?.feedback ?? null;
      } else {
        // Defensive fallback only -- the backend always emits at least one
        // delta on success (see the loop comment above), so this is really
        // just "never silently drop a reply" insurance, not an expected path.
        state.value.messages.push({
          id: String(assistantPayload?.id ?? Date.now() + 1),
          content: assistantPayload?.content || "Désolé, je n'ai pas pu traiter ton message.",
          role: 'assistant',
          timestamp: assistantPayload?.created_at
            ? new Date(assistantPayload.created_at)
            : new Date(),
          sources: assistantPayload?.metadata?.sources,
          toolCalls: assistantPayload?.metadata?.tool_calls,
          tokenUsage: assistantPayload?.metadata?.token_usage,
          feedback: assistantPayload?.feedback ?? null,
        });
      }

      const assistantMessage = state.value.messages[state.value.messages.length - 1];

      state.value.isLoading = false;
      playMessageSound();
      notifyIfHidden(assistantMessage);

      persistLastMessagePreview(assistantMessage);
      onMessage?.(assistantMessage);
      await scrollToBottom();
    } catch (error) {
      state.value.isLoading = false;

      // Escape-to-cancel (Chatbot.vue) aborts the fetch on purpose -- not a
      // failure, so no error banner. Whatever streamed in before the abort
      // (liveMessage above) stays in the thread as-is.
      if (error instanceof DOMException && 'AbortError' === error.name) return;

      console.error("Erreur lors de l'envoi du message:", error);
      state.value.error = isOnline.value ? t('errors.sendFailed') : t('errors.offline');
    } finally {
      activeRequest = null;
      activeToolCall.value = null;
    }
  };

  // Wired to Escape in Chatbot.vue -- stops an in-flight send/regenerate.
  const cancelReply = () => {
    activeRequest?.abort();
  };

  const handleSubmit = (e: Event) => {
    e.preventDefault();
    sendMessage(state.value.inputValue);
  };

  const handleInputChange = (e: Event) => {
    const target = e.target as HTMLInputElement | HTMLTextAreaElement;
    state.value.inputValue = target.value;
    state.value.error = null;
  };

  // Exports the local thread as a Markdown file -- purely client-side, no
  // network call (the conversation already lives in state.value.messages,
  // sources/tool traces excluded on purpose, same "not visitor-facing"
  // reasoning as elsewhere -- see the sources_hidden/tool_calls notes on
  // the backend). A throwaway Blob + <a download>, no library needed.
  const exportConversation = () => {
    if (0 === state.value.messages.length) return;

    const sections = state.value.messages.map((message) => {
      const author = 'user' === message.role ? 'Toi' : 'Assistant';
      const time = message.timestamp.toLocaleString('fr-FR');
      return `**${author}** _(${time})_\n\n${message.content}`;
    });

    const blob = new Blob([sections.join('\n\n---\n\n')], {
      type: 'text/markdown;charset=utf-8',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `conversation-${new Date().toISOString().slice(0, 10)}.md`;
    link.click();
    URL.revokeObjectURL(url);
  };

  // Links directly to Maxime's real, live CV (confirmed with him -- the
  // knowledge base only holds a plain-.txt extraction meant for RAG
  // indexing, not something to hand a visitor) rather than mirroring or
  // re-hosting a copy: it never goes stale if he updates the PDF on his own
  // site, and there's nothing here to keep in sync. No Blob/<a download>
  // technique like exportConversation() above -- download only works
  // reliably same-origin, this is a plain cross-origin navigation instead.
  const CV_URL =
    'https://www.maxime.bzh/cv-maximejolivet-developpeur-web-fullstack-senior-lead-dev-tech-lead-ia.pdf';

  const openCV = () => {
    window.open(CV_URL, '_blank', 'noopener');
  };

  const clearMessages = () => {
    state.value.messages = [];
    state.value.error = null;
    conversationId.value = null;
    localStorage.removeItem(CONVERSATION_ID_STORAGE_KEY);
    localStorage.removeItem(LAST_MESSAGE_PREVIEW_KEY);
    localStorage.removeItem(PINNED_MESSAGES_KEY);
  };

  const setSelectedAgent = (agentId: number | null) => {
    state.value.selectedAgentId = agentId;
  };

  // Thumbs up/down on an assistant reply -- App\Controller\MessageFeedbackController,
  // PATCH /conversations/{id}/messages/{messageId}/feedback. Applied
  // optimistically (the click should feel instant) and rolled back if the
  // request fails; clicking the already-active choice again clears it
  // (feedback: null), same toggle behavior the button in MessageBubble.vue
  // implements.
  const setFeedback = async (messageId: string, feedback: 'positive' | 'negative' | null) => {
    const message = state.value.messages.find((m) => m.id === messageId);
    if (!message || !conversationId.value) return;

    const previous = message.feedback ?? null;
    message.feedback = feedback;

    try {
      await $fetch(`/api/conversations/${conversationId.value}/messages/${messageId}/feedback`, {
        method: 'PATCH',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: { feedback },
      });
    } catch (error) {
      console.error("Erreur lors de l'enregistrement du retour:", error);
      message.feedback = previous;
    }
  };

  // Client-side only (see PINNED_MESSAGES_KEY above) -- no network call,
  // unlike setFeedback.
  const togglePin = (messageId: string) => {
    const message = state.value.messages.find((m) => m.id === messageId);
    if (!message) return;

    message.pinned = !message.pinned;

    const ids = readPinnedIds();
    if (message.pinned) {
      ids.add(messageId);
    } else {
      ids.delete(messageId);
    }
    writePinnedIds(ids);
  };

  // Fetch agents, check LLM connectivity, and restore any previous
  // conversation on mount. restoreConversation is awaited before flushing
  // pendingMessage so a hero-bar question lands after history is loaded,
  // not overwritten by it.
  onMounted(async () => {
    fetchAgents();
    checkLlmStatus();
    await restoreConversation();

    if (pendingMessage.value) {
      const message = pendingMessage.value;
      pendingMessage.value = null;
      sendMessage(message);
    }
  });

  return {
    messages: computed(() => state.value.messages),
    pinnedMessages: computed(() => state.value.messages.filter((m) => m.pinned)),
    togglePin,
    isLoading: computed(() => state.value.isLoading),
    inputValue: computed({
      get: () => state.value.inputValue,
      set: (value) => {
        state.value.inputValue = value;
      },
    }),
    error: computed(() => state.value.error),
    selectedAgentId: computed(() => state.value.selectedAgentId),
    agents: computed(() => state.value.agents),
    llmStatus: computed(() => llmStatus.value),
    isRestoringHistory: computed(() => isRestoringHistory.value),
    toolCallLabel,
    soundMuted: computed(() => soundMuted.value),
    toggleSoundMuted,
    isOnline,
    sendMessage,
    retryLastMessage,
    regenerateLastReply,
    cancelReply,
    handleSubmit,
    handleInputChange,
    clearMessages,
    exportConversation,
    openCV,
    messagesEndRef,
    autoScroll,
    scrollToBottom,
    setSelectedAgent,
    fetchAgents,
    setFeedback,
  };
};
