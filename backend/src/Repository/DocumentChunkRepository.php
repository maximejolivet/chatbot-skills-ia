<?php

namespace App\Repository;

use App\Entity\DocumentChunk;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentChunk>
 */
class DocumentChunkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentChunk::class);
    }

    /**
     * @return DocumentChunk[]
     */
    public function findForDocument(int $documentId): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.document = :documentId')
            ->setParameter('documentId', $documentId)
            ->orderBy('c.chunkIndex', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function deleteForDocument(int $documentId): void
    {
        $this->createQueryBuilder('c')
            ->delete()
            ->andWhere('c.document = :documentId')
            ->setParameter('documentId', $documentId)
            ->getQuery()
            ->execute();
    }
}
