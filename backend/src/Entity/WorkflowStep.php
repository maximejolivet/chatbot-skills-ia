<?php

namespace App\Entity;

use App\Enum\WorkflowStepType;
use App\Repository\WorkflowStepRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A single step in a workflow's execution pipeline. Not exposed as its own
 * API Platform resource -- surfaced through GET/POST /workflows/{id}/steps
 * (no direct CRUD for WorkflowStep).
 */
#[ORM\Entity(repositoryClass: WorkflowStepRepository::class)]
#[ORM\UniqueConstraint(name: 'workflow_step_order_unique', columns: ['workflow_id', '`order`'])]
class WorkflowStep
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workflow::class, inversedBy: 'steps')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workflow $workflow;

    #[ORM\Column(length: 200)]
    private string $name;

    #[ORM\Column(length: 20, enumType: WorkflowStepType::class)]
    private WorkflowStepType $stepType;

    // Quoted column name: "order" is a reserved word in MySQL/MariaDB, same
    // fix as AiProviderConfig.usage -- Doctrine only quotes reserved words in
    // generated SQL when told to via backticks here.
    #[ORM\Column(name: '`order`')]
    private int $order;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column]
    private array $configuration = [];

    #[ORM\Column]
    private bool $isActive = true;

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

    public function getWorkflow(): Workflow
    {
        return $this->workflow;
    }

    public function setWorkflow(Workflow $workflow): static
    {
        $this->workflow = $workflow;

        return $this;
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

    public function getStepType(): WorkflowStepType
    {
        return $this->stepType;
    }

    public function setStepType(WorkflowStepType $stepType): static
    {
        $this->stepType = $stepType;

        return $this;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): static
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function setConfiguration(array $configuration): static
    {
        $this->configuration = $configuration;

        return $this;
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
}
