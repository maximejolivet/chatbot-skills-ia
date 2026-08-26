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
 *
 * search() is hybrid: Qdrant's vector similarity is fused with a lexical
 * (BM25-style) pass over App\Entity\DocumentChunk.content via MariaDB's
 * native FULLTEXT index (see migrations/Version20260822220000.php),
 * combined with Reciprocal Rank Fusion (RRF) rather than a single vector
 * pass -- catches exact-keyword matches (names, dates, error codes) semantic
 * similarity alone sometimes ranks low. Not "real" Qdrant sparse-vector BM25
 * (miniCOIL/Fastembed): that needs a second vectorizer in the stack, a
 * bigger infra change than the payoff warranted here for a single-server
 * deployment with a modest document count.
 *
 * The query embedding itself is cached (QueryEmbeddingCache, Redis) --
 * repeated/frequent identical questions skip the Ollama round-trip for the
 * embed step. Only the embed step: Qdrant search, the lexical pass, and RRF
 * fusion below all still run per call.
 */
class VectorSearchService
{
    public const DEFAULT_COLLECTION_NAME = 'chatbot_embeddings';

    /**
     * RRF constant (k=60 is the value from the original Cormack et al. paper
     * and the one most hybrid-search implementations default to) -- dampens
     * the influence of any single rank-1 hit so one list doesn't dominate
     * the fused order just for topping its own ranking.
     */
    private const int RRF_K = 60;

    /**
     * How many candidates to pull from each leg before fusing, relative to
     * the caller's requested $limit -- fusion needs a wider pool than the
     * final cut to have anything to compare across both lists.
     */
    private const int OVERFETCH_MULTIPLIER = 4;

    public function __construct(
        private readonly QdrantClient $qdrantClient,
        private readonly EmbeddingService $embeddingService,
        private readonly DocumentAnalysisService $documentAnalysisService,
        private readonly VectorIndexRepository $vectorIndexRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly QueryEmbeddingCache $queryEmbeddingCache,
    ) {}

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
        $overfetch = max($limit * self::OVERFETCH_MULTIPLIER, $limit + 20);

        $queryEmbedding = $this->queryEmbeddingCache->remember(
            $query,
            fn(): array => $this->embeddingService->generateEmbedding($query),
        );
        $vectorResults = $this->qdrantClient->search($collectionName, $queryEmbedding, $overfetch, $filterConditions);
        $categoryId = $filterConditions['category_id'] ?? null;
        $lexicalResults = $this->lexicalSearch($query, $overfetch, \is_int($categoryId) ? $categoryId : null);

        $results = $this->fuseResults($vectorResults, $lexicalResults, $limit);

        $this->logSearchQuery($query, $collectionName, count($results), microtime(true) - $start);

        return $results;
    }

    /**
     * BM25-style lexical candidates via MariaDB's native FULLTEXT index (see
     * migrations/Version20260822220000.php) -- a second, independent
     * ranking signal fused with vector similarity in fuseResults().
     *
     * Only `category_id` of VectorSearchController's filter set is applied
     * here (a plain join on Document.category); document_type/language/
     * complexity live only in the Qdrant payload's `intelligent_analysis`
     * metadata (see addDocumentChunks()), not as DocumentChunk/Document
     * columns, so they can't be pushed down into this SQL query without
     * denormalizing them onto the entity -- not done here, out of scope for
     * what hybrid search needed to add. Best-effort: any failure (e.g. a
     * query MariaDB's FULLTEXT parser rejects) logs and degrades to
     * vector-only instead of failing the whole search.
     *
     * @return array<int, array{document_id: int, chunk_index: int, content: string, document_title: string, score: float}>
     */
    private function lexicalSearch(string $query, int $limit, ?int $categoryId = null): array
    {
        if ('' === trim($query)) {
            return [];
        }

        $sql = 'SELECT dc.document_id AS document_id, dc.chunk_index AS chunk_index, dc.content AS content,
                       d.title AS document_title,
                       MATCH(dc.content) AGAINST (:query IN NATURAL LANGUAGE MODE) AS score
                FROM document_chunk dc
                INNER JOIN document d ON d.id = dc.document_id
                WHERE MATCH(dc.content) AGAINST (:query IN NATURAL LANGUAGE MODE)';
        $params = ['query' => $query];

        if (null !== $categoryId) {
            $sql .= ' AND d.category_id = :categoryId';
            $params['categoryId'] = $categoryId;
        }

        $sql .= ' ORDER BY score DESC LIMIT ' . max(1, $limit);

        try {
            $rows = $this->entityManager->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

            return array_map(static fn(array $row): array => [
                'document_id' => self::toInt($row['document_id'] ?? null),
                'chunk_index' => self::toInt($row['chunk_index'] ?? null),
                'content' => self::toStringValue($row['content'] ?? null),
                'document_title' => self::toStringValue($row['document_title'] ?? null),
                'score' => self::toFloat($row['score'] ?? null),
            ], $rows);
        } catch (\Throwable $e) {
            $this->logger->warning('Lexical search failed, continuing with vector results only: {error}', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Reciprocal Rank Fusion: each result's fused score is the sum of
     * 1/(RRF_K + rank) across every list it appears in (rank 0-based), so a
     * chunk found by both signals outranks one found by only one -- without
     * needing to normalize/calibrate cosine similarity against a FULLTEXT
     * relevance score, two numbers on entirely different scales.
     *
     * The `score` field on the returned rows stays a Qdrant cosine
     * similarity whenever the chunk was a vector hit (unchanged meaning for
     * existing consumers -- see docs/backend/SPECIFICATION.md §8.6); only a
     * lexical-only hit (no vector match at all) falls back to the FULLTEXT
     * relevance value, which is not on a 0-1 scale.
     *
     * @param array<int, array{id: string|int, score: float, payload: array<string, mixed>}>                               $vectorResults
     * @param array<int, array{document_id: int, chunk_index: int, content: string, document_title: string, score: float}> $lexicalResults
     *
     * @return array<int, array<string, mixed>>
     */
    private function fuseResults(array $vectorResults, array $lexicalResults, int $limit): array
    {
        $fused = [];

        foreach (array_values($vectorResults) as $rank => $r) {
            $payload = $r['payload'] ?: [];
            $documentId = $payload['document_id'] ?? null;
            $chunkIndex = $payload['chunk_index'] ?? null;
            if (!\is_scalar($documentId) || !\is_scalar($chunkIndex)) {
                continue; // no stable fusion key without both
            }

            $key = "{$documentId}:{$chunkIndex}";
            $fused[$key] ??= [
                'id' => $r['id'],
                'score' => $r['score'],
                'content' => $payload['content'] ?? '',
                'document_id' => $documentId,
                'document_title' => $payload['document_title'] ?? '',
                'chunk_index' => $chunkIndex,
                'metadata' => $payload['metadata'] ?? [],
                'rrf_score' => 0.0,
            ];
            $fused[$key]['rrf_score'] += 1 / (self::RRF_K + $rank);
        }

        foreach (array_values($lexicalResults) as $rank => $r) {
            $key = "{$r['document_id']}:{$r['chunk_index']}";
            $fused[$key] ??= [
                'id' => self::generatePointId((int) $r['document_id'], (int) $r['chunk_index']),
                'score' => (float) $r['score'],
                'content' => $r['content'],
                'document_id' => (int) $r['document_id'],
                'document_title' => $r['document_title'],
                'chunk_index' => (int) $r['chunk_index'],
                'metadata' => [],
                'rrf_score' => 0.0,
            ];
            $fused[$key]['rrf_score'] += 1 / (self::RRF_K + $rank);
        }

        usort($fused, static fn(array $a, array $b): int => $b['rrf_score'] <=> $a['rrf_score']);

        return array_map(
            static function (array $entry): array {
                unset($entry['rrf_score']);

                return $entry;
            },
            array_slice($fused, 0, $limit),
        );
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

            $texts = array_map(static fn(array $chunk): string => $chunk['content'], $chunks);
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

            $searchQuery = new SearchQuery()
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

    private static function toInt(mixed $value): int
    {
        return \is_numeric($value) ? (int) $value : 0;
    }

    private static function toFloat(mixed $value): float
    {
        return \is_numeric($value) ? (float) $value : 0.0;
    }

    private static function toStringValue(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
