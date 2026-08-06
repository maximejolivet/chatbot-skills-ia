<?php

namespace App\AiProvider\Client;

/**
 * A single callable tool offered to the LLM.
 */
final readonly class ToolSpec
{
    /**
     * @param array<string, mixed> $parameters JSON Schema object
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters,
    ) {
    }
}
