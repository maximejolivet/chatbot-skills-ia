<?php

namespace App\Controller;

use App\Entity\Document;
use App\Enum\DocumentStatus;
use App\KnowledgeBase\DocumentIndexingService;
use App\Repository\DocumentChunkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Single canonical (re)processing entry point. Runs synchronously (see
 * DocumentIndexingService) -- this only returns once processing is done.
 */
#[AsController]
final class DocumentProcessController
{
    public function __construct(
        private readonly DocumentChunkRepository $chunkRepository,
        private readonly DocumentIndexingService $documentIndexingService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Document $data): JsonResponse
    {
        $this->chunkRepository->deleteForDocument($data->getId());
        $data->setStatus(DocumentStatus::Pending);
        $this->entityManager->flush();

        try {
            $this->documentIndexingService->chunkDocument($data);
            $this->documentIndexingService->vectorize($data);
        } catch (\Throwable $e) {
            $data->setStatus(DocumentStatus::Error)->setProcessingError($e->getMessage());
            $this->entityManager->flush();

            return new JsonResponse(['status' => 'error', 'error' => $e->getMessage()], 500);
        }

        return new JsonResponse(['status' => $data->getStatus()->value]);
    }
}
