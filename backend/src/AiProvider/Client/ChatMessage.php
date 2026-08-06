<?php

namespace App\AiProvider\Client;

/**
 * A single message in a chat-completion conversation.
 */
final class ChatMessage
{
    /**
     * @param 'system'|'user'|'assistant'|'tool' $role
     * @param ToolCall[]                         $toolCalls set on assistant messages
     */
    public function __construct(
        public string $role,
        public string $content = '',
        public array $toolCalls = [],
        public ?string $toolCallId = null,
        public ?string $name = null,
    ) {
    }
}
