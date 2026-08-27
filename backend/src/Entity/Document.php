<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
use App\Controller\DocumentChunksController;
use App\Controller\DocumentDeleteController;
use App\Controller\DocumentProcessController;
use App\Controller\DocumentUploadController;
use App\Enum\DocumentFileType;
use App\Enum\DocumentStatus;
use App\Repository\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Represents a document in the knowledge base.
 *
 * NOTE: no `uploaded_by` attribution, same as SearchQuery.user and
 * FAQ.created_by -- the User system exists (see Conversation.user,
 * WorkflowExecution.triggeredBy), this entity just hasn't been extended
 * with an owner field, restricted to ROLE_ADMIN as a whole instead.
 */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ApiResource(operations: [
    new GetCollection(),
    new Get(),
    new Patch(),
    new Post(
        controller: DocumentUploadController::class,
        output: false,
        deserialize: false,
        name: 'document_upload',
        openapi: new OpenApiOperation(
            tags: ['Documents'],
            summary: 'Upload a document into the knowledge base',
            description: 'Multipart upload. Persists the document (status "pending") and dispatches it to the async transport for chunking/vectorization -- poll GET /documents/{id} for the final status.',
            requestBody: new RequestBody(
                required: true,
                content: new \ArrayObject([
                    'multipart/form-data' => new MediaType(schema: new \ArrayObject([
                        'type' => 'object',
                        'properties' => [
                            'file' => ['type' => 'string', 'format' => 'binary', 'description' => 'Allowed: pdf, txt, docx, md, html, json. Max 10MB.'],
                            'title' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'category_id' => ['type' => 'integer', 'nullable' => true],
                        ],
                        'required' => ['file', 'title'],
                    ])),
                ]),
            ),
            responses: [
                '202' => new OpenApiResponse(description: 'Document persisted and queued for processing.', content: new \ArrayObject([
                    'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                ])),
                '400' => new OpenApiResponse(description: 'Missing file/title, unsupported file type, or file too large (max 10MB).'),
                '401' => new OpenApiResponse(description: 'Not authenticated.'),
                '403' => new OpenApiResponse(description: 'Authenticated but not ROLE_ADMIN.'),
            ],
        ),
    ),
    new Delete(
        controller: DocumentDeleteController::class,
        output: false,
        read: true,
        name: 'document_delete',
        openapi: new OpenApiOperation(
            tags: ['Documents'],
            summary: 'Delete a document',
            description: 'Removes the Qdrant vectors and chunks, deletes the uploaded file, then removes the row.',
            parameters: [
                new OpenApiParameter(name: 'id', in: 'path', description: 'Document id', required: true, schema: ['type' => 'integer']),
            ],
            responses: [
                '204' => new OpenApiResponse(description: 'Document deleted.'),
                '401' => new OpenApiResponse(description: 'Not authenticated.'),
                '403' => new OpenApiResponse(description: 'Authenticated but not ROLE_ADMIN.'),
                '404' => new OpenApiResponse(description: 'Document not found.'),
            ],
        ),
    ),
    new Post(
        uriTemplate: '/documents/{id}/process',
        controller: DocumentProcessController::class,
        output: false,
        read: true,
        deserialize: false,
        name: 'document_process',
        openapi: new OpenApiOperation(
            tags: ['Documents'],
            summary: '(Re)process a document',
            description: 'Deletes existing chunks, resets status to "pending" and re-dispatches chunking/vectorization to the async transport -- poll GET /documents/{id} for the final status.',
            parameters: [
                new OpenApiParameter(name: 'id', in: 'path', description: 'Document id', required: true, schema: ['type' => 'integer']),
            ],
            responses: [
                '202' => new OpenApiResponse(description: 'Document queued for (re)processing.', content: new \ArrayObject([
                    'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object', 'properties' => ['status' => ['type' => 'string']]])),
                ])),
                '401' => new OpenApiResponse(description: 'Not authenticated.'),
                '403' => new OpenApiResponse(description: 'Authenticated but not ROLE_ADMIN.'),
                '404' => new OpenApiResponse(description: 'Document not found.'),
            ],
        ),
    ),
    new Get(
        uriTemplate: '/documents/{id}/chunks',
        controller: DocumentChunksController::class,
        output: false,
        read: true,
        name: 'document_chunks',
        openapi: new OpenApiOperation(
            tags: ['Documents'],
            summary: 'List the chunks of a document',
            description: 'Returns the raw RAG chunks (content, position, vectorization status) generated for the document.',
            parameters: [
                new OpenApiParameter(name: 'id', in: 'path', description: 'Document id', required: true, schema: ['type' => 'integer']),
            ],
            responses: [
                '200' => new OpenApiResponse(description: 'Chunks of the document.', content: new \ArrayObject([
                    'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'array', 'items' => ['type' => 'object']])),
                ])),
                '401' => new OpenApiResponse(description: 'Not authenticated.'),
                '403' => new OpenApiResponse(description: 'Authenticated but not ROLE_ADMIN.'),
                '404' => new OpenApiResponse(description: 'Document not found.'),
            ],
        ),
    ),
], security: "is_granted('ROLE_ADMIN')")]
class Document implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description = '';

    /**
     * Relative path under the app's upload directory. Nullable to support
     * text-only quick entries (content supplied via `description` instead of
     * an uploaded file) -- see DocumentProcessorService.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column(length: 10, enumType: DocumentFileType::class)]
    private DocumentFileType $fileType;

    #[ORM\ManyToOne(targetEntity: DocumentCategory::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DocumentCategory $category = null;

    #[ORM\ManyToOne(targetEntity: Collection::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Collection $collection = null;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\Column]
    private int $fileSize = 0;

    #[ORM\Column(length: 20, enumType: DocumentStatus::class)]
    private DocumentStatus $status = DocumentStatus::Pending;

    #[ORM\Column(type: 'text')]
    private string $processingError = '';

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column]
    private array $metadata = [];

    /**
     * @var DoctrineCollection<int, DocumentChunk>
     */
    #[ORM\OneToMany(targetEntity: DocumentChunk::class, mappedBy: 'document', orphanRemoval: true)]
    private DoctrineCollection $chunks;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
        $this->chunks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getFileType(): DocumentFileType
    {
        return $this->fileType;
    }

    public function setFileType(DocumentFileType $fileType): static
    {
        $this->fileType = $fileType;

        return $this;
    }

    public function getCategory(): ?DocumentCategory
    {
        return $this->category;
    }

    public function setCategory(?DocumentCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getCollection(): ?Collection
    {
        return $this->collection;
    }

    public function setCollection(?Collection $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function setStatus(DocumentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getProcessingError(): string
    {
        return $this->processingError;
    }

    public function setProcessingError(string $processingError): static
    {
        $this->processingError = $processingError;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * @return DoctrineCollection<int, DocumentChunk>
     */
    public function getChunks(): DoctrineCollection
    {
        return $this->chunks;
    }

    public function addChunk(DocumentChunk $chunk): static
    {
        if (!$this->chunks->contains($chunk)) {
            $this->chunks->add($chunk);
            $chunk->setDocument($this);
        }

        return $this;
    }

    public function getChunkCount(): int
    {
        return $this->chunks->count();
    }
}
