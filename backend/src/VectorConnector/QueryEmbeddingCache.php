<?php

namespace App\VectorConnector;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Caches query embeddings (float[]) so a repeated/frequent question doesn't
 * pay an Ollama round-trip on every VectorSearchService::search() call --
 * one call per chat turn (App\Chat\RagContextService::buildContext()), plus
 * the admin/API vector-search endpoint. Own Redis pool (same pattern as
 * App\Chat\ConversationHistoryCache), TTL from config/packages/cache.yaml.
 *
 * The cache key hashes the trimmed/lowercased query text only -- it carries
 * no notion of which embedding model/provider produced the cached vector.
 * This app already accepts the equivalent tradeoff elsewhere: switching the
 * active embedding model doesn't auto-reindex existing document chunks
 * either (Document::status / POST /documents/{id}/process re-processes on
 * demand). Switching the embedding model here means clearing this pool (or
 * waiting out the TTL) -- not attempting automatic invalidation on a config
 * change that's a rare, deliberate ops action, not a runtime concern.
 *
 * Deliberately not `final`: VectorSearchServiceTest stubs this directly
 * (`createStub()`) rather than wiring a real CacheInterface, the same reason
 * VectorSearchService itself is left non-final -- see that class's docblock.
 */
class QueryEmbeddingCache
{
    public function __construct(
        #[Autowire(service: 'cache.query_embedding')]
        private readonly CacheInterface $cache,
    ) {}

    /**
     * @param callable(): float[] $compute
     *
     * @return float[]
     */
    public function remember(string $query, callable $compute): array
    {
        return $this->cache->get($this->key($query), static fn() => $compute());
    }

    private function key(string $query): string
    {
        return 'query_embedding.' . hash('xxh128', mb_strtolower(trim($query)));
    }
}
