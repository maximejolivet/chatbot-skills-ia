<?php

namespace App\AiProvider\Client;

final readonly class CompletionResult
{
    /**
     * @param array<string, mixed> $usage
     */
    public function __construct(
        public ChatMessage $message,
        public array $usage,
    ) {
    }
}
