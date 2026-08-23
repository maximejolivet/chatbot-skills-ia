<?php

namespace App\Tests\KnowledgeBase;

use App\Entity\Collection;
use App\Entity\Document;
use App\Entity\VectorIndex;
use App\KnowledgeBase\CollectionService;
use App\Repository\CollectionRepository;
use App\VectorConnector\QdrantClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * QdrantClient is `final` -- real instance, wired with a MockHttpClient at
 * its own constructor seam (see QdrantClientTest for the same technique).
 */
final class CollectionServiceTest extends TestCase
{
    private function service(
        CollectionRepository $repository,
        ?EntityManagerInterface $entityManager = null,
        ?QdrantClient $qdrantClient = null,
    ): CollectionService {
        return new CollectionService(
            $repository,
            $qdrantClient ?? new QdrantClient(
                new MockHttpClient(fn(): MockResponse => new MockResponse('{}', ['http_code' => 200])),
                $this->createStub(LoggerInterface::class),
                'qdrant-host',
                '6333',
                '',
            ),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    public function testDocumentWithOwnCollectionUsesItsQdrantName(): void
    {
        $vectorIndex = new VectorIndex()->setName('idx')->setDescription('')->setCollectionId('own_qdrant_name')->setDimension(1024);
        $collection = new Collection()->setName('Own')->setDescription('')->setVectorIndex($vectorIndex);
        $document = new Document()->setTitle('doc')->setCollection($collection);

        $repository = $this->createMock(CollectionRepository::class);
        $repository->expects(self::never())->method('findCommon');

        $name = $this->service($repository)->getQdrantCollectionNameForDocument($document);

        self::assertSame('own_qdrant_name', $name);
    }

    public function testDocumentWithoutCollectionFallsBackToExistingCommonCollection(): void
    {
        $vectorIndex = new VectorIndex()->setName('idx')->setDescription('')->setCollectionId('common_qdrant_name')->setDimension(1024);
        $common = new Collection()->setName('Commune')->setDescription('')->setIsCommon(true)->setVectorIndex($vectorIndex);

        $repository = $this->createStub(CollectionRepository::class);
        $repository->method('findCommon')->willReturn($common);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $document = new Document()->setTitle('doc');
        $name = $this->service($repository, $entityManager)->getQdrantCollectionNameForDocument($document);

        self::assertSame('common_qdrant_name', $name);
    }

    public function testEnsureCommonCollectionCreatesOneWhenNoneExists(): void
    {
        $repository = $this->createStub(CollectionRepository::class);
        $repository->method('findCommon')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(2))->method('persist')->with(self::logicalOr(
            self::isInstanceOf(Collection::class),
            self::isInstanceOf(VectorIndex::class),
        ));

        $common = $this->service($repository, $entityManager)->ensureCommonCollection();

        self::assertTrue($common->isCommon());
        self::assertSame('Collection Commune', $common->getName());
        self::assertNotNull($common->getVectorIndex());
        self::assertStringStartsWith('collection_common_', $common->getVectorIndex()->getCollectionId());
    }

    public function testEnsureCommonCollectionSwallowsQdrantFailure(): void
    {
        $repository = $this->createStub(CollectionRepository::class);
        $repository->method('findCommon')->willReturn(null);

        $failingQdrant = new QdrantClient(
            new MockHttpClient(fn() => throw new \RuntimeException('qdrant down')),
            $this->createStub(LoggerInterface::class),
            'qdrant-host',
            '6333',
            '',
        );

        // ensureCollection()'s own try/catch (see QdrantClient) would normally
        // swallow this, but this test targets CollectionService's own
        // try/catch around the call -- proves ensureCommonCollection() still
        // returns the Collection rather than propagating, even if Qdrant's
        // own resilience were ever removed.
        $common = $this->service($repository, $this->createStub(EntityManagerInterface::class), $failingQdrant)
            ->ensureCommonCollection();

        self::assertTrue($common->isCommon());
    }

    public function testGetQdrantCollectionNameForAgentDelegatesToRepository(): void
    {
        $vectorIndex = new VectorIndex()->setName('idx')->setDescription('')->setCollectionId('agent_qdrant_name')->setDimension(1024);
        $collection = new Collection()->setName('Agent collection')->setDescription('')->setVectorIndex($vectorIndex);

        $repository = $this->createStub(CollectionRepository::class);
        $repository->method('findOneByAgent')->willReturn($collection);

        self::assertSame('agent_qdrant_name', $this->service($repository)->getQdrantCollectionNameForAgent(7));
    }

    public function testGetQdrantCollectionNameForAgentReturnsNullWhenNoneFound(): void
    {
        $repository = $this->createStub(CollectionRepository::class);
        $repository->method('findOneByAgent')->willReturn(null);

        self::assertNull($this->service($repository)->getQdrantCollectionNameForAgent(7));
    }
}
