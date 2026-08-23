<?php

declare(strict_types=1);

namespace App\VectorConnector;

use App\AiProvider\ProviderSelectionService;

/**
 * Generates embeddings via whichever provider ProviderSelectionService resolves.
 *
 * The provider is re-resolved on every call (not cached on the service) so that
 * an admin-managed AiProviderConfig change takes effect immediately, without
 * requiring this (singleton, DI-managed) service to be re-instantiated.
 */
final class EmbeddingService
{
    /** @var array<string, mixed>|null */
    private ?array $lastUsage = null;

    /** @var array<string, mixed>|null */
    private ?array $batchUsage = null;

    public function __construct(
        private readonly ProviderSelectionService $providerSelectionService,
    ) {}

    /**
     * @return float[]
     */
    public function generateEmbedding(string $text): array
    {
        $result = $this->providerSelectionService->getEmbeddingClient()->embed($text);
        $this->lastUsage = $result->usage;

        return $result->vector;
    }

    /**
     * @param string[] $texts
     *
     * @return array<int, float[]>
     */
    public function generateEmbeddingsBatch(array $texts): array
    {
        $results = $this->providerSelectionService->getEmbeddingClient()->embedBatch($texts);
        $totalTokens = array_sum(array_map(static fn(\App\AiProvider\Client\EmbeddingResult $r) => $r->usage['total_tokens'] ?? 0, $results));

        $this->batchUsage = [
            'total_tokens' => $totalTokens,
            'source' => $results[0]->usage['source'] ?? 'estimated',
            'provider' => $results[0]->usage['provider'] ?? null,
            'model' => $results[0]->usage['model'] ?? null,
            'items' => count($texts),
        ];

        return array_map(static fn(\App\AiProvider\Client\EmbeddingResult $r): array => $r->vector, $results);
    }

    /**
     * @return array<string, mixed>
     */
    public function checkStatus(): array
    {
        return $this->providerSelectionService->getEmbeddingClient()->checkStatus();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastUsage(): ?array
    {
        return $this->lastUsage;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBatchUsage(): ?array
    {
        return $this->batchUsage;
    }
}
