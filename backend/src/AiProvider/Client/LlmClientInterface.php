<?php

namespace App\AiProvider\Client;

/**
 * A chat-completion client for a single configured model.
 */
interface LlmClientInterface
{
    /**
     * @param ChatMessage[]     $messages
     * @param ToolSpec[]|null   $tools
     */
    public function complete(
        array $messages,
        ?array $tools = null,
        float $temperature = 0.7,
        int $maxTokens = 3000,
    ): CompletionResult;

    /**
     * Plain text streaming only -- no tools.
     *
     * @param ChatMessage[] $messages
     *
     * @return iterable<string>
     */
    public function stream(
        array $messages,
        float $temperature = 0.7,
        int $maxTokens = 3000,
    ): iterable;

    /**
     * @return array<string, mixed>
     */
    public function checkStatus(): array;
}
