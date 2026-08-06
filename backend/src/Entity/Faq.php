<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\FaqRepository;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * NOTE: no `created_by` attribution, same as Document.uploaded_by -- no
 * auth/User system yet.
 */
#[ORM\Entity(repositoryClass: FaqRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource]
class Faq implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 500)]
    private string $question;

    #[ORM\Column(type: 'text')]
    private string $answer;

    #[ORM\ManyToOne(targetEntity: DocumentCategory::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?DocumentCategory $category = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column]
    private bool $isActive = true;

    /**
     * @var array<int, string>
     */
    #[ORM\Column]
    private array $tags = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): static
    {
        $this->answer = $answer;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
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
     * @return array<int, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param array<int, string> $tags
     */
    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }
}
