<?php

declare(strict_types=1);

namespace App\Chat;

final readonly class ChatReplyResult
{
    /**
     * @param array<string, mixed>             $usage
     * @param array<int, array<string, mixed>> $toolCalls
     * @param array<int, array<string, mixed>> $sources
     */
    public function __construct(
        public string $content,
        public array $usage,
        public array $toolCalls = [],
        public array $sources = [],
    ) {}
}
