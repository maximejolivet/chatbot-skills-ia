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
  pinned?: boolean;
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
}

export interface ChatbotState {
  messages: Message[];
  isLoading: boolean;
  inputValue: string;
  error: string | null;
  selectedAgentId: number | null;
  agents: AIAgent[];
}
