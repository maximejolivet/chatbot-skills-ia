export interface MessageSource {
  document_id: number;
  document_title: string | null;
  score: number | null;
}

export interface ToolCallTrace {
  tool: string;
  arguments: Record<string, unknown>;
  status: string;
  output: unknown;
}

export interface TokenUsage {
  prompt_tokens: number;
  completion_tokens: number;
  total_tokens: number;
  source: string;
  provider: string;
  model: string;
}

export interface Message {
  id: string;
  content: string;
  role: 'user' | 'assistant';
  timestamp: Date;
  isTyping?: boolean;
  sources?: MessageSource[];
  toolCalls?: ToolCallTrace[];
  tokenUsage?: TokenUsage;
  feedback?: 'positive' | 'negative' | null;
}

// Payload emitted by MessageBubble.vue's identity/email cards (see
// asksForIdentity/asksForEmail there) -- Chatbot.vue turns it into the
// natural-language sentence sent to the model, no backend change needed.
export interface InterviewBookingSubmission {
  firstName: string;
  lastName: string;
  email: string;
  objet: string;
  date: string;
  modalite: 'visio' | 'telephone';
  // Only meaningful (and only required) for modalite === 'telephone'.
  telephone: string;
}

export interface AIAgent {
  id: number;
  name: string;
  description: string;
  is_active: boolean;
}

export interface ChatbotProps {
  title?: string;
  theme?: 'light' | 'dark';
  apiUrl?: string;
  placeholder?: string;
  className?: string;
  showClose?: boolean;
  /** 'widget': fixed-size floating card. 'page': fills its container, no fullscreen toggle. */
  variant?: 'widget' | 'page';
  /** Host page's own dark/light state, forwarded by StickyChatBubble.vue when embedded
   * (public/widget.js detects it on the host page and passes it through) -- overrides
   * `theme`/OS preference, but not a visitor's own explicit in-widget toggle. See
   * composables/useColorScheme.ts. */
  hostScheme?: 'light' | 'dark' | null;
}

export interface ChatbotState {
  messages: Message[];
  isLoading: boolean;
  inputValue: string;
  error: string | null;
  selectedAgentId: number | null;
  agents: AIAgent[];
}
