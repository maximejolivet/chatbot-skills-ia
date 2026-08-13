<?php

namespace App\KnowledgeBase;

use App\Entity\Collection;
use App\Entity\Document;
use App\Entity\VectorIndex;
use App\Repository\CollectionRepository;
use App\VectorConnector\QdrantClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves and bootstraps the Qdrant collection a document's chunks should live in.
 *
 * Deliberate design choice: instead of falling back to a hardcoded collection
 * name string when no common Collection row exists yet (which would silently
 * diverge from the DB if a bootstrap step was never run), the common
 * collection is created lazily and eagerly here on first need.
 */
class CollectionService
{
    public function __construct(
        private readonly CollectionRepository $collectionRepository,
        private readonly QdrantClient $qdrantClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Resolve the Qdrant collection name a document's chunks should live in: its own
     * collection if set, else the common collection (created on demand).
     */
    public function getQdrantCollectionNameForDocument(Document $document): string
    {
        if ($document->getCollection()) {
            return $document->getCollection()->getCollectionNameForQdrant();
        }

        return $this->ensureCommonCollection()->getCollectionNameForQdrant();
    }

    public function getQdrantCollectionNameForAgent(int $agentId): ?string
    {
        $collection = $this->collectionRepository->findOneByAgent($agentId);

        return $collection?->getCollectionNameForQdrant();
    }

    /**
     * Idempotent bootstrap of the common collection.
     */
    public function ensureCommonCollection(): Collection
    {
        $common = $this->collectionRepository->findCommon();
        if ($common) {
            return $common;
        }

        $common = (new Collection())
            ->setName('Collection Commune')
            ->setDescription('Collection commune pour les documents sans collection spécifique')
            ->setIsCommon(true);
        $this->entityManager->persist($common);
        $this->entityManager->flush();

        $collectionId = "collection_common_{$common->getId()}";
        $vectorIndex = (new VectorIndex())
            ->setName('Index Collection Commune')
            ->setDescription('Vector index for the common collection')
            ->setCollectionId($collectionId)
            ->setDimension(1024)
            ->setIsActive(true);
        $this->entityManager->persist($vectorIndex);
        $common->setVectorIndex($vectorIndex);
        $this->entityManager->flush();

        try {
            $this->qdrantClient->ensureCollection($collectionId);
        } catch (\Throwable $e) {
            $this->logger->warning('Could not create Qdrant collection for the common collection: {error}', [
                'error' => $e->getMessage(),
            ]);
        }

        return $common;
    }
}
