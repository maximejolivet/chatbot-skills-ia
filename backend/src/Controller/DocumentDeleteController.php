<?php

namespace App\Controller;

use App\Entity\Document;
use App\KnowledgeBase\DocumentIndexingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    // See DocumentUploadController's comment -- Document's resource-level
    // security doesn't apply to custom-controller operations.
    #[IsGranted('ROLE_ADMIN')]
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
