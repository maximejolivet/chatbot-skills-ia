<?php

namespace App\Chat;

final readonly class ChatReplyResult
{
    /**
     * @param array<string, mixed>       $usage
     * @param array<int, array<string, mixed>> $toolCalls
     */
    public function __construct(
        public string $content,
        public array $usage,
        public array $toolCalls = [],
    ) {
    }
}
