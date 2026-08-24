<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\FaqRepository;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * NOTE: no `created_by` attribution, same as Document.uploaded_by -- the
 * User system exists (see Conversation.user), this entity just hasn't been
 * extended with an owner field.
 *
 * Managed exclusively through the /admin backoffice (see
 * config/routes/admin.yaml, app_admin_faq) -- no write operations exposed
 * here, same reasoning as AiAgent. GetCollection/Get are deliberately public
 * (PUBLIC_ACCESS): the landing page and chat widget pull FAQ questions as
 * conversation starters (see frontend/composables/useFaqs.ts). Inactive FAQs
 * are excluded from the public collection by FaqActiveCollectionExtension --
 * admins still see them all in the /admin/faqs grid, a separate query.
 */
#[ORM\Entity(repositoryClass: FaqRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(operations: [
    new GetCollection(paginationEnabled: false, security: "is_granted('PUBLIC_ACCESS')"),
    new Get(security: "is_granted('PUBLIC_ACCESS')"),
], security: "is_granted('ROLE_ADMIN')")]
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

    // Display order for the public collection (GetCollection, see
    // FaqActiveCollectionExtension's ORDER BY) and the /admin/faqs grid --
    // ascending, lower value shown first. Defaults to 0 (new FAQs land at
    // the front until an admin resequences them); no auto-increment-to-last
    // logic, the expected FAQ count for this project is small enough that
    // manually typing a number in the admin form is simpler than adding
    // reordering machinery for it.
    #[ORM\Column]
    private int $priority = 0;

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

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;

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
