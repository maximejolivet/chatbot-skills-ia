<?php

namespace App\MessageHandler;

use App\Entity\Document;
use App\Enum\DocumentStatus;
use App\KnowledgeBase\DocumentIndexingService;
use App\Message\IndexDocumentMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs on the `async` transport worker -- same chunk+vectorize logic
 * DocumentUploadController/DocumentProcessController used to call inline
 * within the HTTP request (see DocumentIndexingService).
 */
#[AsMessageHandler]
final class IndexDocumentMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentIndexingService $documentIndexingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(IndexDocumentMessage $message): void
    {
        $document = $this->entityManager->find(Document::class, $message->documentId);
        if (!$document instanceof Document) {
            $this->logger->warning('IndexDocumentMessage for missing document {id}, skipping.', ['id' => $message->documentId]);

            return;
        }

        try {
            $this->documentIndexingService->chunkDocument($document);
            $this->documentIndexingService->vectorize($document);
        } catch (\Throwable $e) {
            $this->logger->error('Document {id} indexing failed: {error}', ['id' => $document->getId(), 'error' => $e->getMessage()]);
            $document->setStatus(DocumentStatus::Error)->setProcessingError($e->getMessage());
            $this->entityManager->flush();
        }
    }
}
