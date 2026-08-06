<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\CollectionRepository;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * A collection of documents, optionally scoped to an AI agent.
 */
#[ORM\Entity(repositoryClass: CollectionRepository::class)]
#[ApiResource]
class Collection implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200, unique: true)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description = '';

    /**
     * AI Agent associated with this collection. If null, this is the common collection.
     */
    #[ORM\OneToOne(inversedBy: 'collection', targetEntity: AiAgent::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?AiAgent $agent = null;

    #[ORM\ManyToOne(targetEntity: VectorIndex::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?VectorIndex $vectorIndex = null;

    /**
     * Whether this is the common collection for documents without a specific collection.
     */
    #[ORM\Column]
    private bool $isCommon = false;

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

    public function getAgent(): ?AiAgent
    {
        return $this->agent;
    }

    public function setAgent(?AiAgent $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getVectorIndex(): ?VectorIndex
    {
        return $this->vectorIndex;
    }

    public function setVectorIndex(?VectorIndex $vectorIndex): static
    {
        $this->vectorIndex = $vectorIndex;

        return $this;
    }

    public function isCommon(): bool
    {
        return $this->isCommon;
    }

    public function setIsCommon(bool $isCommon): static
    {
        $this->isCommon = $isCommon;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCollectionNameForQdrant(): string
    {
        if ($this->vectorIndex) {
            return $this->vectorIndex->getCollectionId();
        }

        return sprintf('collection_%d_%s', $this->id, str_replace(' ', '_', mb_strtolower($this->name)));
    }
}
