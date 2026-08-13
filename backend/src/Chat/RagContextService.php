<?php

namespace App\Chat;

use App\Entity\AiAgent;
use App\KnowledgeBase\CollectionService;
use App\VectorConnector\VectorSearchService;
use Psr\Log\LoggerInterface;

/**
 * Resolves which Qdrant collection to search (via knowledge_base) and
 * performs the search (via vector_connector).
 */
final class RagContextService
{
    public function __construct(
        private readonly CollectionService $collectionService,
        private readonly VectorSearchService $vectorSearchService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildContext(string $query, ?AiAgent $agent = null, int $limit = 5): array
    {
        // The agent's own collection is the primary source (e.g. a CV
        // vectorized under a recruitment agent). When no agent is resolved,
        // or the agent has no dedicated collection, fall back to the real
        // common collection instead of VectorSearchService's hardcoded
        // default name, which no ingestion path ever writes to.
        $collectionName = $agent ? $this->collectionService->getQdrantCollectionNameForAgent($agent->getId()) : null;
        $collectionName ??= $this->collectionService->ensureCommonCollection()->getCollectionNameForQdrant();

        try {
            return $this->vectorSearchService->search(query: $query, collectionName: $collectionName, limit: $limit);
        } catch (\Throwable $e) {
            $this->logger->error('RAG search failed for query {query}: {error}', [
                'query' => mb_substr($query, 0, 100),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
