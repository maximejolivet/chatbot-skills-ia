<?php

namespace App\Controller;

use App\Entity\Document;
use App\KnowledgeBase\DocumentIndexingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cleans up Qdrant vectors and the uploaded file before removing the row --
 * the default API Platform delete operation would only remove the DB row.
 */
#[AsController]
final readonly class DocumentDeleteController
{
    public function __construct(
        private DocumentIndexingService $documentIndexingService,
        private EntityManagerInterface $entityManager,
        #[Autowire('%app.document_upload_dir%')]
        private string $uploadDir,
    ) {}

    public function __invoke(Document $data): Response
    {
        $this->documentIndexingService->deleteVectorsAndChunks($data);

        if ($data->getFilePath()) {
            @unlink($this->uploadDir . '/' . $data->getFilePath());
        }

        $this->entityManager->remove($data);
        $this->entityManager->flush();

        return new Response(null, 204);
    }
}
