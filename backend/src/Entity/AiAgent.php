<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Repository\AiAgentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * An AI agent with its own system prompt and set of workflow tools.
 *
 * Read-only via the REST API (GetCollection/Get only); agents are managed
 * through the /admin backoffice instead (see config/routes/admin.yaml).
 * Pagination disabled on the API: a small, bounded list that frontends
 * expect to receive in full.
 */
#[ORM\Entity(repositoryClass: AiAgentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    security: "is_granted('ROLE_ADMIN')",
    operations: [
        new GetCollection(paginationEnabled: false),
        new Get(),
    ],
)]
class AiAgent implements ResourceInterface
{
    public const DEFAULT_SYSTEM_PROMPT = <<<'PROMPT'
        Tu es un assistant IA utile et bienveillant spécialisé dans l'aide aux utilisateurs.
        Tu réponds en français de manière claire et concise.
        PROMPT;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200, unique: true)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(type: 'text')]
    private string $systemPrompt = self::DEFAULT_SYSTEM_PROMPT;

    /**
     * @var DoctrineCollection<int, Workflow>
     */
    #[ORM\ManyToMany(targetEntity: Workflow::class, inversedBy: 'agents')]
    #[ORM\JoinTable(name: 'ai_agent_workflow')]
    private DoctrineCollection $workflows;

    #[ORM\OneToOne(mappedBy: 'agent', targetEntity: Collection::class)]
    private ?Collection $collection = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->workflows = new ArrayCollection();
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

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function setSystemPrompt(string $systemPrompt): static
    {
        $this->systemPrompt = $systemPrompt;

        return $this;
    }

    /**
     * @return DoctrineCollection<int, Workflow>
     */
    public function getWorkflows(): DoctrineCollection
    {
        return $this->workflows;
    }

    public function addWorkflow(Workflow $workflow): static
    {
        if (!$this->workflows->contains($workflow)) {
            $this->workflows->add($workflow);
        }

        return $this;
    }

    public function removeWorkflow(Workflow $workflow): static
    {
        $this->workflows->removeElement($workflow);

        return $this;
    }

    /**
     * @return DoctrineCollection<int, Workflow>
     */
    public function getActiveWorkflows(): DoctrineCollection
    {
        return $this->workflows->filter(static fn (Workflow $w) => $w->isActive());
    }

    public function getCollection(): ?Collection
    {
        return $this->collection;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
