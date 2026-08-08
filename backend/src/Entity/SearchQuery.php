<?php

namespace App\Entity;

use App\Repository\SearchQueryRepository;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Log of search queries for analytics. Not exposed as its own API Platform
 * resource -- surfaced read-only through the /vector/stats action (no direct
 * CRUD for SearchQuery). Also read-only in the admin backoffice (see
 * config/routes/admin.yaml).
 *
 * NOTE: no `user` attribution -- the User system exists (see
 * Conversation.user), this entity just hasn't been extended with an owner
 * field, that link is dropped for now rather than half-built.
 */
#[ORM\Entity(repositoryClass: SearchQueryRepository::class)]
class SearchQuery implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 500)]
    private string $query;

    #[ORM\ManyToOne(targetEntity: VectorIndex::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private VectorIndex $vectorIndex;

    #[ORM\Column]
    private int $resultsCount;

    #[ORM\Column]
    private float $executionTime;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

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

    public function getQuery(): string
    {
        return $this->query;
    }

    public function setQuery(string $query): static
    {
        $this->query = $query;

        return $this;
    }

    public function getVectorIndex(): VectorIndex
    {
        return $this->vectorIndex;
    }

    public function setVectorIndex(VectorIndex $vectorIndex): static
    {
        $this->vectorIndex = $vectorIndex;

        return $this;
    }

    public function getResultsCount(): int
    {
        return $this->resultsCount;
    }

    public function setResultsCount(int $resultsCount): static
    {
        $this->resultsCount = $resultsCount;

        return $this;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    public function setExecutionTime(float $executionTime): static
    {
        $this->executionTime = $executionTime;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
