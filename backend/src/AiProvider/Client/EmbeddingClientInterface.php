<?php

declare(strict_types=1);

namespace App\AiProvider\Client;

interface EmbeddingClientInterface
{
    public function embed(string $text): EmbeddingResult;

    /**
     * @param string[] $texts
     *
     * @return EmbeddingResult[]
     */
    public function embedBatch(array $texts): array;

    /**
     * @return array<string, mixed>
     */
    public function checkStatus(): array;
}
