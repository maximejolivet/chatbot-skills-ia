<?php

namespace App\Entity;

use App\Repository\DocumentChunkRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents a chunk of a document for vector indexing. Not exposed as its
 * own API Platform resource -- surfaced read-only through
 * GET /documents/{id}/chunks (no direct CRUD for DocumentChunk).
 */
#[ORM\Entity(repositoryClass: DocumentChunkRepository::class)]
#[ORM\UniqueConstraint(name: 'document_chunk_index_unique', columns: ['document_id', 'chunk_index'])]
class DocumentChunk
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Document::class, inversedBy: 'chunks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column]
    private int $chunkIndex;

    #[ORM\Column]
    private int $startPosition;

    #[ORM\Column]
    private int $endPosition;

    /**
     * Qdrant point ID once this chunk has been vectorized.
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $vectorId = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column]
    private array $metadata = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): static
    {
        $this->document = $document;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getChunkIndex(): int
    {
        return $this->chunkIndex;
    }

    public function setChunkIndex(int $chunkIndex): static
    {
        $this->chunkIndex = $chunkIndex;

        return $this;
    }

    public function getStartPosition(): int
    {
        return $this->startPosition;
    }

    public function setStartPosition(int $startPosition): static
    {
        $this->startPosition = $startPosition;

        return $this;
    }

    public function getEndPosition(): int
    {
        return $this->endPosition;
    }

    public function setEndPosition(int $endPosition): static
    {
        $this->endPosition = $endPosition;

        return $this;
    }

    public function getVectorId(): ?string
    {
        return $this->vectorId;
    }

    public function setVectorId(?string $vectorId): static
    {
        $this->vectorId = $vectorId;

        return $this;
    }

    public function isVectorized(): bool
    {
        return null !== $this->vectorId && '' !== $this->vectorId;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
