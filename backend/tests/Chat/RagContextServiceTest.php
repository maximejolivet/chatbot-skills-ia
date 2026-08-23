<?php

namespace App\Tests\Chat;

use App\Chat\RagContextService;
use App\Entity\AiAgent;
use App\Entity\Collection;
use App\KnowledgeBase\CollectionService;
use App\VectorConnector\VectorSearchService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RagContextServiceTest extends TestCase
{
    private function agentWithId(int $id): AiAgent
    {
        $agent = new AiAgent();
        new \ReflectionProperty(AiAgent::class, 'id')->setValue($agent, $id);

        return $agent;
    }

    public function testUsesAgentOwnCollectionWhenAvailable(): void
    {
        $collectionService = $this->createMock(CollectionService::class);
        $collectionService->expects(self::once())
            ->method('getQdrantCollectionNameForAgent')
            ->with(42)
            ->willReturn('agent_42_collection');
        $collectionService->expects(self::never())->method('ensureCommonCollection');

        $vectorSearchService = $this->createMock(VectorSearchService::class);
        $vectorSearchService->expects(self::once())
            ->method('search')
            ->with(query: 'hello', collectionName: 'agent_42_collection', limit: 5)
            ->willReturn([['document_id' => 1]]);

        $service = new RagContextService($collectionService, $vectorSearchService, $this->createStub(LoggerInterface::class));

        self::assertSame([['document_id' => 1]], $service->buildContext('hello', $this->agentWithId(42)));
    }

    public function testFallsBackToCommonCollectionWhenAgentHasNone(): void
    {
        $collectionService = $this->createMock(CollectionService::class);
        $collectionService->method('getQdrantCollectionNameForAgent')->willReturn(null);

        $commonCollection = $this->createStub(Collection::class);
        $commonCollection->method('getCollectionNameForQdrant')->willReturn('common_collection');
        $collectionService->expects(self::once())->method('ensureCommonCollection')->willReturn($commonCollection);

        $vectorSearchService = $this->createMock(VectorSearchService::class);
        $vectorSearchService->expects(self::once())
            ->method('search')
            ->with(query: 'hello', collectionName: 'common_collection', limit: 5)
            ->willReturn([]);

        $service = new RagContextService($collectionService, $vectorSearchService, $this->createStub(LoggerInterface::class));

        $service->buildContext('hello', $this->agentWithId(99));
    }

    public function testFallsBackToCommonCollectionWhenNoAgent(): void
    {
        $collectionService = $this->createMock(CollectionService::class);
        $collectionService->expects(self::never())->method('getQdrantCollectionNameForAgent');

        $commonCollection = $this->createStub(Collection::class);
        $commonCollection->method('getCollectionNameForQdrant')->willReturn('common_collection');
        $collectionService->expects(self::once())->method('ensureCommonCollection')->willReturn($commonCollection);

        $vectorSearchService = $this->createMock(VectorSearchService::class);
        $vectorSearchService->expects(self::once())
            ->method('search')
            ->with(query: 'hello', collectionName: 'common_collection', limit: 5)
            ->willReturn([]);

        $service = new RagContextService($collectionService, $vectorSearchService, $this->createStub(LoggerInterface::class));

        $service->buildContext('hello');
    }

    public function testReturnsEmptyArrayAndLogsWhenSearchThrows(): void
    {
        $collectionService = $this->createStub(CollectionService::class);
        $commonCollection = $this->createStub(Collection::class);
        $commonCollection->method('getCollectionNameForQdrant')->willReturn('common_collection');
        $collectionService->method('ensureCommonCollection')->willReturn($commonCollection);

        $vectorSearchService = $this->createStub(VectorSearchService::class);
        $vectorSearchService->method('search')->willThrowException(new \RuntimeException('Qdrant unreachable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $service = new RagContextService($collectionService, $vectorSearchService, $logger);

        self::assertSame([], $service->buildContext('hello'));
    }
}
