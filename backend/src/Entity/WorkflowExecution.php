<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Enum\WorkflowExecutionStatus;
use App\Repository\WorkflowExecutionRepository;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * A single execution (run) of a workflow.
 *
 * `triggeredBy` is the operator account authenticated on the request that
 * started this execution, stamped automatically (see UserStampListener).
 * Null for executions triggered before multi-user auth existed (admin-only,
 * see OwnershipVoter). ROLE_USER accounts are restricted to their own
 * executions via the `security` expression below plus
 * OwnershipCollectionExtension for GetCollection.
 */
#[ORM\Entity(repositoryClass: WorkflowExecutionRepository::class)]
#[ApiResource(operations: [new GetCollection(), new Get(security: "is_granted('OWNER', object)")])]
class WorkflowExecution implements ResourceInterface, OwnedResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Workflow::class, inversedBy: 'executions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Workflow $workflow;

    /**
     * Set when this execution was triggered by the LLM as a tool call during
     * a chat turn. Null for API-triggered executions, and for tool calls
     * made from the anonymous quick-send chat.
     */
    #[ORM\ManyToOne(targetEntity: Conversation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Conversation $conversation = null;

    // Not readable/writable over the API -- see Conversation::$user for why
    // (User has no serialization groups, so embedding it would leak the
    // password hash).
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $triggeredBy = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column]
    private array $inputData = [];

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column]
    private array $outputData = [];

    #[ORM\Column(length: 20, enumType: WorkflowExecutionStatus::class)]
    private WorkflowExecutionStatus $status = WorkflowExecutionStatus::Pending;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'text')]
    private string $errorMessage = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    #[ORM\Column]
    private array $executionLog = [];

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

    public function getConversation(): ?Conversation
    {
        return $this->conversation;
    }

    public function setConversation(?Conversation $conversation): static
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function getTriggeredBy(): ?User
    {
        return $this->triggeredBy;
    }

    public function setTriggeredBy(?User $triggeredBy): static
    {
        $this->triggeredBy = $triggeredBy;

        return $this;
    }

    public function getOwnerUser(): ?User
    {
        return $this->triggeredBy;
    }

    public static function getOwnerFieldName(): string
    {
        return 'triggeredBy';
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputData(): array
    {
        return $this->inputData;
    }

    /**
     * @param array<string, mixed> $inputData
     */
    public function setInputData(array $inputData): static
    {
        $this->inputData = $inputData;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOutputData(): array
    {
        return $this->outputData;
    }

    /**
     * @param array<string, mixed> $outputData
     */
    public function setOutputData(array $outputData): static
    {
        $this->outputData = $outputData;

        return $this;
    }

    public function getStatus(): WorkflowExecutionStatus
    {
        return $this->status;
    }

    public function setStatus(WorkflowExecutionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getExecutionLog(): array
    {
        return $this->executionLog;
    }

    /**
     * @param array<int, array<string, mixed>> $executionLog
     */
    public function setExecutionLog(array $executionLog): static
    {
        $this->executionLog = $executionLog;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
