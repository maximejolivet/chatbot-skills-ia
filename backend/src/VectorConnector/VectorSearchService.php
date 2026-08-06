<?php

namespace App\VectorConnector;

use App\Entity\SearchQuery;
use App\Repository\VectorIndexRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * RAG orchestration: search, index, delete. Collection names are always
 * provided by the caller -- this service does not know about agents, documents,
 * or collections as domain concepts (that's knowledge_base's job).
 */
final class VectorSearchService
{
    public const DEFAULT_COLLECTION_NAME = 'chatbot_embeddings';

    public function __construct(
        private readonly QdrantClient $qdrantClient,
        private readonly EmbeddingService $embeddingService,
        private readonly DocumentAnalysisService $documentAnalysisService,
        private readonly VectorIndexRepository $vectorIndexRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Deterministic UUID so the same point ID can be regenerated later for deletion.
     */
    public static function generatePointId(int $documentId, int $chunkIndex): string
    {
        return Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_DNS), "doc_{$documentId}_chunk_{$chunkIndex}")->toRfc4122();
    }

    /**
     * @param array<string, mixed>|null $filterConditions
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(
        string $query,
        ?string $collectionName = null,
        int $limit = 10,
        ?array $filterConditions = null,
    ): array {
        $collectionName ??= self::DEFAULT_COLLECTION_NAME;
        $start = microtime(true);

        $queryEmbedding = $this->embeddingService->generateEmbedding($query);
        $rawResults = $this->qdrantClient->search($collectionName, $queryEmbedding, $limit, $filterConditions);

        $results = array_map(static function (array $r): array {
            $payload = $r['payload'] ?: [];

            return [
                'id' => $r['id'],
                'score' => $r['score'],
                'content' => $payload['content'] ?? '',
                'document_id' => $payload['document_id'] ?? null,
                'document_title' => $payload['document_title'] ?? '',
                'chunk_index' => $payload['chunk_index'] ?? null,
                'metadata' => $payload['metadata'] ?? [],
            ];
        }, $rawResults);

        $this->logSearchQuery($query, $collectionName, count($results), microtime(true) - $start);

        return $results;
    }

    /**
     * Analyze, embed, and store chunks in Qdrant. Returns the point IDs per
     * chunk_index so the caller (knowledge_base) can persist vector_id on each
     * DocumentChunk.
     *
     * @param array<int, array{content: string, chunk_index: int, metadata?: array<string, mixed>}> $chunks
     */
    public function addDocumentChunks(
        int $documentId,
        string $collectionName,
        array $chunks,
        string $documentContent = '',
        string $documentFilename = '',
    ): IndexingResult {
        try {
            $intelligentMetadata = '' !== $documentContent
                ? $this->documentAnalysisService->analyzeDocument($documentContent, $documentFilename)
                : DocumentAnalysisService::DEFAULT_DOCUMENT_METADATA;

            $texts = array_map(static fn (array $chunk) => $chunk['content'], $chunks);
            $embeddings = $this->embeddingService->generateEmbeddingsBatch($texts);

            $points = [];
            $chunkPointIds = [];
            foreach ($chunks as $i => $chunk) {
                $embedding = $embeddings[$i];
                $pointId = self::generatePointId($documentId, $chunk['chunk_index']);
                $chunkPointIds[$chunk['chunk_index']] = $pointId;

                $chunkMetadata = $chunk['metadata'] ?? [];
                $enhancedMetadata = [
                    ...$chunkMetadata,
                    'intelligent_analysis' => $intelligentMetadata,
                    'document_type' => $intelligentMetadata['document_type'] ?? 'document',
                    'category' => $intelligentMetadata['category'] ?? 'général',
                    'language' => $intelligentMetadata['language'] ?? 'fr',
                    'complexity' => $intelligentMetadata['complexity'] ?? 'intermédiaire',
                    'target_audience' => $intelligentMetadata['target_audience'] ?? 'général',
                    'relevance_score' => $intelligentMetadata['relevance_score'] ?? 5,
                    'sentiment' => $intelligentMetadata['sentiment'] ?? 'neutre',
                    'confidence' => $intelligentMetadata['confidence'] ?? 1,
                ];

                $points[] = [
                    'id' => $pointId,
                    'vector' => $embedding,
                    'payload' => [
                        'content' => $chunk['content'],
                        'document_id' => $documentId,
                        'document_title' => $chunkMetadata['document_title'] ?? '',
                        'chunk_index' => $chunk['chunk_index'],
                        'category_id' => $chunkMetadata['category_id'] ?? null,
                        'metadata' => $enhancedMetadata,
                        'document_type' => $intelligentMetadata['document_type'] ?? 'document',
                        'category' => $intelligentMetadata['category'] ?? 'général',
                        'language' => $intelligentMetadata['language'] ?? 'fr',
                        'keywords' => $intelligentMetadata['keywords'] ?? [],
                        'topics' => $intelligentMetadata['topics'] ?? [],
                        'complexity' => $intelligentMetadata['complexity'] ?? 'intermédiaire',
                        'relevance_score' => $intelligentMetadata['relevance_score'] ?? 5,
                    ],
                ];
            }

            $this->qdrantClient->upsert($collectionName, $points);

            return new IndexingResult(
                success: true,
                chunkPointIds: $chunkPointIds,
                embeddingUsage: $this->embeddingService->getBatchUsage(),
                analysisMetadata: $intelligentMetadata,
            );
        } catch (\Throwable $e) {
            $this->logger->error('Error adding document chunks for document {id}: {error}', [
                'id' => $documentId,
                'error' => $e->getMessage(),
            ]);

            return new IndexingResult(success: false, error: $e->getMessage());
        }
    }

    /**
     * @param array<int, string> $pointIds
     */
    public function deleteDocumentChunks(string $collectionName, array $pointIds): bool
    {
        if (!$pointIds) {
            return true;
        }

        return $this->qdrantClient->delete($collectionName, $pointIds);
    }

    /**
     * Attribute the log entry to the VectorIndex actually searched, if one is
     * registered for that collection.
     */
    private function logSearchQuery(string $query, string $collectionName, int $resultsCount, float $executionTime): void
    {
        try {
            $vectorIndex = $this->vectorIndexRepository->findOneByCollectionId($collectionName);
            if (!$vectorIndex) {
                $this->logger->debug('No VectorIndex registered for collection "{collection}"; skipping search-query log.', [
                    'collection' => $collectionName,
                ]);

                return;
            }

            $searchQuery = (new SearchQuery())
                ->setQuery($query)
                ->setVectorIndex($vectorIndex)
                ->setResultsCount($resultsCount)
                ->setExecutionTime($executionTime);

            $this->entityManager->persist($searchQuery);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Error logging search query: {error}', ['error' => $e->getMessage()]);
        }
    }
}
