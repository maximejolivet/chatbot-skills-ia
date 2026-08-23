<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
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
    ),
    new Delete(
        controller: DocumentDeleteController::class,
        output: false,
        read: true,
        name: 'document_delete',
    ),
    new Post(
        uriTemplate: '/documents/{id}/process',
        controller: DocumentProcessController::class,
        output: false,
        read: true,
        deserialize: false,
        name: 'document_process',
    ),
    new Get(
        uriTemplate: '/documents/{id}/chunks',
        controller: DocumentChunksController::class,
        output: false,
        read: true,
        name: 'document_chunks',
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
