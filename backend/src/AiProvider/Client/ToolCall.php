<?php

declare(strict_types=1);

namespace App\AiProvider\Client;

/**
 * A tool invocation requested by the model. `arguments` is always an already-parsed
 * array, regardless of how the underlying provider encodes it on the wire (Ollama
 * returns an object; OpenAI-compatible APIs return a JSON string).
 */
final readonly class ToolCall
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {}
}
