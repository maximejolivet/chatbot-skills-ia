<?php

namespace App\Repository;

use App\Entity\Document;
use App\KnowledgeBase\DocumentIndexingService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface as SyliusRepositoryInterface;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository implements SyliusRepositoryInterface
{
    use ResourceRepositoryTrait {
        remove as private removeResource;
    }

    public function __construct(
        ManagerRegistry $registry,
        private readonly DocumentIndexingService $documentIndexingService,
        #[Autowire('%app.document_upload_dir%')]
        private readonly string $uploadDir,
    ) {
        parent::__construct($registry, Document::class);
    }

    /**
     * Overrides ResourceRepositoryTrait::remove() to clean up Qdrant vectors and
     * the uploaded file too -- same cleanup as DocumentDeleteController (the API's
     * own delete endpoint), so deleting a Document from the backoffice doesn't
     * leave orphans behind.
     */
    public function remove(ResourceInterface $resource): void
    {
        \assert($resource instanceof Document);

        $this->documentIndexingService->deleteVectorsAndChunks($resource);
        if ($resource->getFilePath()) {
            @unlink($this->uploadDir . '/' . $resource->getFilePath());
        }

        $this->removeResource($resource);
    }

    /**
     * @return Document[]
     */
    public function search(?int $categoryId = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('d')->orderBy('d.uploadedAt', 'DESC');

        if ($categoryId) {
            $qb->andWhere('d.category = :categoryId')->setParameter('categoryId', $categoryId);
        }
        if ($status) {
            $qb->andWhere('d.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }
}
