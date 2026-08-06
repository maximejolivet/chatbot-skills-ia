<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\VectorIndexRepository;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Represents a vector index (Qdrant collection) known to the app.
 */
#[ORM\Entity(repositoryClass: VectorIndexRepository::class)]
#[ApiResource]
class VectorIndex implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(length: 100, unique: true)]
    private string $collectionId;

    /** mxbai-embed-large embedding dimension */
    #[ORM\Column]
    private int $dimension = 1024;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private bool $isActive = true;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column]
    private array $metadata = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getCollectionId(): string
    {
        return $this->collectionId;
    }

    public function setCollectionId(string $collectionId): static
    {
        $this->collectionId = $collectionId;

        return $this;
    }

    public function getDimension(): int
    {
        return $this->dimension;
    }

    public function setDimension(int $dimension): static
    {
        $this->dimension = $dimension;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

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
}
