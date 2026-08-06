<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Enum\DocumentFileType;
use App\Enum\DocumentStatus;
use App\KnowledgeBase\DocumentIndexingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Mirrors knowledge_base.views.DocumentViewSet.create() + DocumentUploadSerializer
 * validation. Runs chunking/vectorization synchronously (see DocumentIndexingService).
 */
#[AsController]
final class DocumentUploadController
{
    private const ALLOWED_EXTENSIONS = ['pdf', 'txt', 'docx', 'md', 'html', 'json'];
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentIndexingService $documentIndexingService,
        #[Autowire('%app.document_upload_dir%')]
        private readonly string $uploadDir,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('Missing file.');
        }

        $title = trim((string) $request->request->get('title', ''));
        if ('' === $title) {
            throw new BadRequestHttpException('Missing title.');
        }

        $extension = mb_strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), \PATHINFO_EXTENSION));
        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequestHttpException(sprintf(
                'File type not supported. Allowed types: %s',
                implode(', ', array_map(static fn (string $e) => ".{$e}", self::ALLOWED_EXTENSIONS)),
            ));
        }
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new BadRequestHttpException('File size too large. Maximum size is 10MB.');
        }

        $categoryId = $request->request->get('category_id');
        $category = $categoryId ? $this->entityManager->getReference(DocumentCategory::class, (int) $categoryId) : null;

        $document = (new Document())
            ->setTitle($title)
            ->setDescription((string) $request->request->get('description', ''))
            ->setFileType(DocumentFileType::from($extension))
            ->setCategory($category);

        $this->entityManager->persist($document);
        $this->entityManager->flush(); // assigns an id, used for the upload subdirectory

        $categorySegment = $category?->getId() ?? 'uncategorized';
        $filename = uniqid('', true).'_'.$file->getClientOriginalName();
        $relativePath = "{$categorySegment}/{$filename}";
        $fileSize = $file->getSize();
        $file->move($this->uploadDir.'/'.$categorySegment, $filename);

        $document->setFilePath($relativePath)->setFileSize($fileSize);
        $this->entityManager->flush();

        try {
            $this->documentIndexingService->chunkDocument($document);
            $this->documentIndexingService->vectorize($document);
        } catch (\Throwable $e) {
            $document->setStatus(DocumentStatus::Error)->setProcessingError($e->getMessage());
            $this->entityManager->flush();
        }

        return new JsonResponse([
            'id' => $document->getId(),
            'title' => $document->getTitle(),
            'description' => $document->getDescription(),
            'file_type' => $document->getFileType()->value,
            'category_id' => $document->getCategory()?->getId(),
            'uploaded_at' => $document->getUploadedAt()->format(\DATE_ATOM),
            'file_size' => $document->getFileSize(),
            'status' => $document->getStatus()->value,
            'processing_error' => $document->getProcessingError(),
            'metadata' => $document->getMetadata(),
            'chunk_count' => $document->getChunkCount(),
        ], 201);
    }
}
