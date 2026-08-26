<?php

namespace App\KnowledgeBase;

use App\Entity\Document;
use App\Entity\DocumentChunk;
use App\Enum\DocumentStatus;
use App\Repository\DocumentChunkRepository;
use App\VectorConnector\VectorSearchService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Orchestrates the document -> chunks -> vectors pipeline. This is the single
 * place that decides Document::status, and it only marks a document 'indexed'
 * once Qdrant has actually confirmed the vectors were stored.
 *
 * Called from the `async` Messenger transport worker
 * (App\MessageHandler\IndexDocumentMessageHandler), not from the HTTP
 * request directly -- see DocumentUploadController/DocumentProcessController.
 */
final readonly class DocumentIndexingService
{
    public function __construct(
        private DocumentProcessorService $processor,
        private DocumentChunkRepository $chunkRepository,
        private CollectionService $collectionService,
        private VectorSearchService $vectorSearchService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        #[Autowire('%app.document_upload_dir%')]
        private string $uploadDir,
    ) {}

    /**
     * Extract text and persist chunks. Returns the chunk count. Throws on failure
     * (caller is responsible for catching and setting status='error').
     */
    public function chunkDocument(Document $document): int
    {
        $document->setStatus(DocumentStatus::Processing);
        $this->entityManager->flush();

        $chunksData = $this->processor->processDocument($document, $this->absoluteFilePath($document));

        foreach ($chunksData as $index => $data) {
            $chunk = new DocumentChunk()
                ->setContent($data['content'])
                ->setChunkIndex($index)
                ->setStartPosition($data['start_position'])
                ->setEndPosition($data['end_position'])
                ->setMetadata($data['metadata']);
            $document->addChunk($chunk);
            $this->entityManager->persist($chunk);
        }
        $this->entityManager->flush();

        return count($chunksData);
    }

    /**
     * Embed and store this document's chunks in Qdrant. Sets status to 'indexed'
     * only on confirmed success, 'error' (with a real message) otherwise.
     */
    public function vectorize(Document $document): bool
    {
        $documentId = $document->getId() ?? throw new \LogicException('Document must be persisted.');

        $collectionName = $this->collectionService->getQdrantCollectionNameForDocument($document);
        $chunks = $this->chunkRepository->findForDocument($documentId);
        if (!$chunks) {
            $this->logger->warning('No chunks found for document {id}; nothing to vectorize.', ['id' => $documentId]);

            return false;
        }

        $documentContent = $this->readDocumentContent($document, $chunks);

        $chunkData = array_map(static fn(DocumentChunk $c): array => [
            'content' => $c->getContent(),
            'chunk_index' => $c->getChunkIndex(),
            'metadata' => $c->getMetadata(),
        ], $chunks);

        $result = $this->vectorSearchService->addDocumentChunks(
            documentId: $documentId,
            collectionName: $collectionName,
            chunks: $chunkData,
            documentContent: $documentContent,
            documentFilename: $document->getTitle(),
        );

        if (!$result->success) {
            $document->setStatus(DocumentStatus::Error)->setProcessingError($result->error ?? 'Vectorization failed');
            $this->entityManager->flush();

            return false;
        }

        $chunksByIndex = [];
        foreach ($chunks as $chunk) {
            $chunksByIndex[$chunk->getChunkIndex()] = $chunk;
        }
        foreach ($result->chunkPointIds as $chunkIndex => $vectorId) {
            if (isset($chunksByIndex[$chunkIndex])) {
                $chunksByIndex[$chunkIndex]->setVectorId($vectorId);
            }
        }

        if ($result->embeddingUsage) {
            $metadata = $document->getMetadata();
            $metadata['embedding_usage'] = $result->embeddingUsage;
            $document->setMetadata($metadata);
        }
        $document->setStatus(DocumentStatus::Indexed);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Clean up a document's Qdrant vectors before removing its chunks.
     */
    public function deleteVectorsAndChunks(Document $document): void
    {
        $documentId = $document->getId() ?? throw new \LogicException('Document must be persisted.');

        $collectionName = $this->collectionService->getQdrantCollectionNameForDocument($document);
        $chunks = $this->chunkRepository->findForDocument($documentId);

        $pointIds = array_map(
            static fn(DocumentChunk $c): string => $c->getVectorId() ?: VectorSearchService::generatePointId($documentId, $c->getChunkIndex()),
            $chunks,
        );

        if ($pointIds) {
            $this->vectorSearchService->deleteDocumentChunks($collectionName, $pointIds);
        }

        $this->chunkRepository->deleteForDocument($documentId);
    }

    public function absoluteFilePath(Document $document): ?string
    {
        return $document->getFilePath() ? $this->uploadDir . '/' . $document->getFilePath() : null;
    }

    /**
     * Prefer the original file's full text for analysis; fall back to the
     * concatenated chunk content.
     *
     * @param DocumentChunk[] $chunks
     */
    private function readDocumentContent(Document $document, array $chunks): string
    {
        $absolutePath = $this->absoluteFilePath($document);
        if ($absolutePath && is_file($absolutePath)) {
            $content = @file_get_contents($absolutePath);
            if (false !== $content) {
                return $content;
            }
            $this->logger->warning('Could not read document file {path} for analysis.', ['path' => $absolutePath]);
        }

        return implode(' ', array_map(static fn(DocumentChunk $c): string => $c->getContent(), $chunks));
    }
}
