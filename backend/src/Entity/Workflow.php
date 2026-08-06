<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\WorkflowSoftDeleteController;
use App\Controller\WorkflowStepsController;
use App\Controller\WorkflowTestController;
use App\Controller\WorkflowTriggerController;
use App\Enum\WorkflowStatus;
use App\Enum\WorkflowTriggerType;
use App\Repository\WorkflowRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * A workflow that can be triggered via the API or as an agent tool.
 *
 * NOTE: no `created_by` attribution, same as Document.uploaded_by -- no
 * auth/User system yet, so there are no ownership checks either.
 */
#[ORM\Entity(repositoryClass: WorkflowRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(
            controller: WorkflowSoftDeleteController::class,
            read: true,
            output: false,
            name: 'workflow_soft_delete',
        ),
        new Post(
            uriTemplate: '/workflows/{id}/trigger',
            controller: WorkflowTriggerController::class,
            read: true,
            output: false,
            name: 'workflow_trigger',
        ),
        new Post(
            uriTemplate: '/workflows/{id}/test',
            controller: WorkflowTestController::class,
            read: true,
            output: false,
            name: 'workflow_test',
        ),
        new Get(
            uriTemplate: '/workflows/{id}/steps',
            controller: WorkflowStepsController::class,
            read: true,
            output: false,
            name: 'workflow_steps',
        ),
        new Post(
            uriTemplate: '/workflows/{id}/steps',
            controller: WorkflowStepsController::class,
            read: true,
            deserialize: false,
            output: false,
            name: 'workflow_steps_create',
        ),
    ],
)]
class Workflow implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Also used as the tool name when called by an LLM.
     */
    #[ORM\Column(length: 200, unique: true)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description = '';

    #[ORM\Column(length: 20, enumType: WorkflowTriggerType::class)]
    private WorkflowTriggerType $triggerType = WorkflowTriggerType::Manual;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column]
    private array $triggerConfig = [];

    /**
     * JSON Schema describing the expected input_data. Used as the tool
     * 'parameters' definition when this workflow is offered to an LLM.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column]
    private array $parametersSchema = [];

    #[ORM\Column(length: 20, enumType: WorkflowStatus::class)]
    private WorkflowStatus $status = WorkflowStatus::Draft;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column]
    private bool $isActive = true;

    /**
     * @var DoctrineCollection<int, WorkflowStep>
     */
    #[ORM\OneToMany(targetEntity: WorkflowStep::class, mappedBy: 'workflow', orphanRemoval: true)]
    #[ORM\OrderBy(['order' => 'ASC'])]
    private DoctrineCollection $steps;

    /**
     * @var DoctrineCollection<int, WorkflowExecution>
     */
    #[ORM\OneToMany(targetEntity: WorkflowExecution::class, mappedBy: 'workflow')]
    private DoctrineCollection $executions;

    /**
     * @var DoctrineCollection<int, AiAgent>
     */
    #[ORM\ManyToMany(targetEntity: AiAgent::class, mappedBy: 'workflows')]
    private DoctrineCollection $agents;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->steps = new ArrayCollection();
        $this->executions = new ArrayCollection();
        $this->agents = new ArrayCollection();
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

    public function getTriggerType(): WorkflowTriggerType
    {
        return $this->triggerType;
    }

    public function setTriggerType(WorkflowTriggerType $triggerType): static
    {
        $this->triggerType = $triggerType;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTriggerConfig(): array
    {
        return $this->triggerConfig;
    }

    /**
     * @param array<string, mixed> $triggerConfig
     */
    public function setTriggerConfig(array $triggerConfig): static
    {
        $this->triggerConfig = $triggerConfig;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParametersSchema(): array
    {
        return $this->parametersSchema;
    }

    /**
     * @param array<string, mixed> $parametersSchema
     */
    public function setParametersSchema(array $parametersSchema): static
    {
        $this->parametersSchema = $parametersSchema;

        return $this;
    }

    public function getStatus(): WorkflowStatus
    {
        return $this->status;
    }

    public function setStatus(WorkflowStatus $status): static
    {
        $this->status = $status;

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
     * @return DoctrineCollection<int, WorkflowStep>
     */
    public function getSteps(): DoctrineCollection
    {
        return $this->steps;
    }

    public function addStep(WorkflowStep $step): static
    {
        if (!$this->steps->contains($step)) {
            $this->steps->add($step);
            $step->setWorkflow($this);
        }

        return $this;
    }

    /**
     * @return DoctrineCollection<int, WorkflowExecution>
     */
    public function getExecutions(): DoctrineCollection
    {
        return $this->executions;
    }

    public function getExecutionCount(): int
    {
        return $this->executions->count();
    }

    /**
     * @return DoctrineCollection<int, AiAgent>
     */
    public function getAgents(): DoctrineCollection
    {
        return $this->agents;
    }
}
