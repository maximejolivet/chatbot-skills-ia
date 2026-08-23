<?php

namespace App\Tests\VectorConnector;

use App\AiProvider\ProviderSelectionService;
use App\Repository\AiProviderConfigRepository;
use App\Repository\VectorIndexRepository;
use App\VectorConnector\DocumentAnalysisService;
use App\VectorConnector\EmbeddingService;
use App\VectorConnector\QdrantClient;
use App\VectorConnector\QueryEmbeddingCache;
use App\VectorConnector\VectorSearchService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * QdrantClient/EmbeddingService/DocumentAnalysisService/ProviderSelectionService
 * are all `final`, so none can be doubled directly. EmbeddingService and
 * DocumentAnalysisService are real instances here, built on a real (but
 * never-called in these tests) ProviderSelectionService -- deleteDocumentChunks()
 * is the only method under test and it never touches either. QdrantClient is
 * real too, but wired with a MockHttpClient at its own constructor seam, same
 * technique as QdrantClientTest.
 */
final class VectorSearchServiceTest extends TestCase
{
    private function service(?QdrantClient $qdrantClient = null, ?EntityManagerInterface $entityManager = null): VectorSearchService
    {
        $providerSelection = new ProviderSelectionService(
            $this->createStub(AiProviderConfigRepository::class),
            $this->createStub(LoggerInterface::class),
            'ollama',
            '',
            '',
            '',
            30,
            'http://ollama.test:11434',
            'chat-model',
            'embed-model',
            'analysis-model',
        );

        return new VectorSearchService(
            $qdrantClient ?? new QdrantClient(
                new MockHttpClient(fn() => throw new \LogicException('QdrantClient should not be called here')),
                $this->createStub(LoggerInterface::class),
                'qdrant-host',
                '6333',
                '',
            ),
            new EmbeddingService($providerSelection),
            new DocumentAnalysisService($providerSelection, $this->createStub(LoggerInterface::class)),
            $this->createStub(VectorIndexRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(QueryEmbeddingCache::class),
        );
    }

    private function invokePrivate(VectorSearchService $service, string $method, array $args): mixed
    {
        return new \ReflectionMethod($service, $method)->invoke($service, ...$args);
    }

    public function testGeneratePointIdIsDeterministic(): void
    {
        $first = VectorSearchService::generatePointId(42, 3);
        $second = VectorSearchService::generatePointId(42, 3);

        self::assertSame($first, $second);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $first);
    }

    public function testGeneratePointIdDiffersByChunkIndex(): void
    {
        self::assertNotSame(
            VectorSearchService::generatePointId(42, 0),
            VectorSearchService::generatePointId(42, 1),
        );
    }

    public function testDeleteDocumentChunksWithNoPointIdsIsANoOp(): void
    {
        // Wired with a QdrantClient that throws if ever called -- proves
        // the empty-array short-circuit really never reaches Qdrant.
        self::assertTrue($this->service()->deleteDocumentChunks('my_collection', []));
    }

    public function testDeleteDocumentChunksDelegatesToQdrantClient(): void
    {
        $qdrantClient = new QdrantClient(
            new MockHttpClient(fn(): MockResponse => new MockResponse('{}', ['http_code' => 200])),
            $this->createStub(LoggerInterface::class),
            'qdrant-host',
            '6333',
            '',
        );

        self::assertTrue($this->service($qdrantClient)->deleteDocumentChunks('my_collection', ['point-1']));
    }

    private function vectorHit(int $documentId, int $chunkIndex, float $score, string $content = 'vector content'): array
    {
        return [
            'id' => "vec-{$documentId}-{$chunkIndex}",
            'score' => $score,
            'payload' => [
                'document_id' => $documentId,
                'chunk_index' => $chunkIndex,
                'content' => $content,
                'document_title' => "Doc {$documentId}",
                'metadata' => ['language' => 'fr'],
            ],
        ];
    }

    private function lexicalHit(int $documentId, int $chunkIndex, float $score, string $content = 'lexical content'): array
    {
        return [
            'document_id' => $documentId,
            'chunk_index' => $chunkIndex,
            'content' => $content,
            'document_title' => "Doc {$documentId}",
            'score' => $score,
        ];
    }

    public function testFuseResultsRanksHitFoundByBothSignalsFirst(): void
    {
        // Chunk 2 only ranks #2 in vector results and #2 in lexical results,
        // but appearing in both should still out-rank chunk 1 (which is #1
        // in vector but absent from lexical) under RRF.
        $vector = [$this->vectorHit(1, 0, 0.9), $this->vectorHit(2, 0, 0.8)];
        $lexical = [$this->lexicalHit(3, 0, 10.0), $this->lexicalHit(2, 0, 8.0)];

        $result = $this->invokePrivate($this->service(), 'fuseResults', [$vector, $lexical, 10]);

        self::assertSame(2, $result[0]['document_id']);
    }

    public function testFuseResultsKeepsVectorScoreForHitsFoundInBothLists(): void
    {
        $vector = [$this->vectorHit(1, 0, 0.42)];
        $lexical = [$this->lexicalHit(1, 0, 99.0)];

        $result = $this->invokePrivate($this->service(), 'fuseResults', [$vector, $lexical, 10]);

        self::assertCount(1, $result);
        self::assertSame(0.42, $result[0]['score']);
    }

    public function testFuseResultsUsesLexicalScoreAndGeneratedIdForLexicalOnlyHits(): void
    {
        $result = $this->invokePrivate($this->service(), 'fuseResults', [[], [$this->lexicalHit(5, 2, 7.5, 'only in lexical')], 10]);

        self::assertCount(1, $result);
        self::assertSame(7.5, $result[0]['score']);
        self::assertSame('only in lexical', $result[0]['content']);
        self::assertSame(VectorSearchService::generatePointId(5, 2), $result[0]['id']);
        self::assertSame([], $result[0]['metadata']);
    }

    public function testFuseResultsSkipsVectorHitsMissingDocumentIdOrChunkIndex(): void
    {
        $malformed = ['id' => 'x', 'score' => 0.9, 'payload' => ['content' => 'no ids here']];

        $result = $this->invokePrivate($this->service(), 'fuseResults', [[$malformed], [], 10]);

        self::assertSame([], $result);
    }

    public function testFuseResultsRespectsLimit(): void
    {
        $vector = array_map(fn(int $i): array => $this->vectorHit($i, 0, 1.0 - $i * 0.01), range(1, 5));

        $result = $this->invokePrivate($this->service(), 'fuseResults', [$vector, [], 2]);

        self::assertCount(2, $result);
    }

    private function connectionReturning(array $rows): EntityManagerInterface
    {
        $dbalResult = $this->createStub(Result::class);
        $dbalResult->method('fetchAllAssociative')->willReturn($rows);

        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willReturn($dbalResult);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }

    public function testLexicalSearchReturnsEmptyArrayForBlankQuery(): void
    {
        // No Connection wired at all -- proves the blank-query short-circuit
        // never even reaches getConnection().
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getConnection');

        $result = $this->invokePrivate($this->service(entityManager: $entityManager), 'lexicalSearch', ['   ', 10, null]);

        self::assertSame([], $result);
    }

    public function testLexicalSearchReturnsRowsFromFulltextQuery(): void
    {
        $rows = [$this->lexicalHit(9, 0, 3.2)];
        $service = $this->service(entityManager: $this->connectionReturning($rows));

        $result = $this->invokePrivate($service, 'lexicalSearch', ['some query', 10, null]);

        self::assertSame($rows, $result);
    }

    public function testLexicalSearchDegradesToEmptyArrayOnDatabaseFailure(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeQuery')->willThrowException(new \RuntimeException('syntax error'));
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $result = $this->invokePrivate($this->service(entityManager: $entityManager), 'lexicalSearch', ['some query', 10, null]);

        self::assertSame([], $result);
    }
}
