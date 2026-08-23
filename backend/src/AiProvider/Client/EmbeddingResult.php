<?php

declare(strict_types=1);

namespace App\AiProvider\Client;

final readonly class EmbeddingResult
{
    /**
     * @param float[]              $vector
     * @param array<string, mixed> $usage
     */
    public function __construct(
        public array $vector,
        public array $usage,
    ) {}
}
