<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter as OpenApiParameter;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response as OpenApiResponse;
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
 * NOTE: no `created_by` attribution, same as Document.uploaded_by, and no
 * ownership checks -- the User system exists (see WorkflowExecution.
 * triggeredBy), this entity just hasn't been extended with an owner field,
 * restricted to ROLE_ADMIN as a whole instead.
 */
#[ORM\Entity(repositoryClass: WorkflowRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(operations: [
    new GetCollection(),
    new Get(),
    new Post(),
    new Patch(),
    new Delete(
        controller: WorkflowSoftDeleteController::class,
        output: false,
        read: true,
        name: 'workflow_soft_delete',
        openapi: new OpenApiOperation(
            tags: ['Workflows'],
            summary: 'Soft-delete a workflow',
            description: 'Sets is_active=false rather than removing the row.',
            parameters: [
                new OpenApiParameter(name: 'id', in: 'path', description: 'Workflow id', required: true, schema: ['type' => 'integer']),
            ],
            responses: [
                '204' => new OpenApiResponse(description: 'Workflow deactivated.'),
                '401' => new OpenApiResponse(description: 'Not authenticated.'),
                '403' => new OpenApiResponse(description: 'Authenticated but not ROLE_ADMIN.'),
                '404' => new OpenApiResponse(description: 'Workflow not found.'),
            ],
        ),
    ),
    new Post(
        uriTemplate: '/workflows/{id}/trigger',
        controller: WorkflowTriggerController::class,
        output: false,
        read: true,
        name: 'workflow_trigger',
        openapi: new OpenApiOperation(
            tags: ['Workflows'],
            summary: 'Trigger a workflow execution',
            description: 'Creates a pending WorkflowExecution and dispatches it to the async transport -- returns as soon as it is queued, not once it is done; poll GET /workflow_executions/{id} for the final status. Requires the workflow to be active.',
            parameters: [
                new OpenApiParameter(name: 'id', in: 'path', description: 'Workflow id', required: true, schema: ['type' => 'integer']),
            ],
            requestBody: new RequestBody(
                content: new \ArrayObject([
                    'application/json' => new MediaType(schema: new \ArrayObject([
                        'type' => 'object',
                        'properties' => ['input_data' => ['type' => 'object', 'description' => 'Arbitrary input passed to the first workflow step.']],
                    ])),
                ]),
            ),
            responses: [
                '202' => new OpenApiResponse(description: 'The pending WorkflowExecution.', content: new \ArrayObject([
                    'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                ])),
                '400' => new OpenApiResponse(description: 'Workflow is not active.'),
                '401' => new OpenApiResponse(description: 'Not authenticated.'),
                '403' => new OpenApiResponse(description: 'Authenticated but not ROLE_ADMIN.'),
                '404' => new OpenApiResponse(description: 'Workflow not found.'),
            ],
        ),
    ),
    new Post(
        uriTemplate: '/workflows/{id}/test',
        controller: WorkflowTestController::class,
        output: false,
        read: true,
        name: 'workflow_test',
        openapi: new OpenApiOperation(
            tags: ['Workflows'],
            summary: 'Test-run a workflow execution',
            description: 'Same async pattern as /trigger, but with no is-active check -- lets an inactive/draft workflow be exercised.',
            parameters: [
                new OpenApiParameter(name: 'id', in: 'path', description: 'Workflow id', required: true, schema: ['type' => 'integer']),
            ],
            requestBody: new RequestBody(
                content: new \ArrayObject([
                    'application/json' => new MediaType(schema: new \ArrayObject([
                        'type' => 'object',
                        'properties' => ['input_data' => ['type' => 'object', 'description' => 'Arbitrary input passed to the first workflow step.']],
                    ])),
                ]),
            ),
            responses: [
                '202' => new OpenApiResponse(description: 'The pending WorkflowExecution.', content: new \ArrayObject([
                    'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                ])),
                '401' => new OpenApiResponse(description: 'Not authenticated.'),
                '403' => new OpenApiResponse(description: 'Authenticated but not ROLE_ADMIN.'),
                '404' => new OpenApiResponse(description: 'Workflow not found.'),
            ],
        ),
    ),
    new Get(
        uriTemplate: '/workflows/{id}/steps',
        controller: WorkflowStepsController::class,
        output: false,
        read: true,
        name: 'workflow_steps',
        openapi: new OpenApiOperation(
            tags: ['Workflows'],
            summary: 'List the active steps of a workflow',
            description: 'Returns the workflow steps, ordered.',
            parameters: [
                new OpenApiParameter(name: 'id', in: 'path', description: 'Workflow id', required: true, schema: ['type' => 'integer']),
            ],
            responses: [
                '200' => new OpenApiResponse(description: 'Steps of the workflow.', content: new \ArrayObject([
                    'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'array', 'items' => ['type' => 'object']])),
                ])),
                '401' => new OpenApiResponse(description: 'Not authenticated.'),
                '403' => new OpenApiResponse(description: 'Authenticated but not ROLE_ADMIN.'),
                '404' => new OpenApiResponse(description: 'Workflow not found.'),
            ],
        ),
    ),
    new Post(
        uriTemplate: '/workflows/{id}/steps',
        controller: WorkflowStepsController::class,
        output: false,
        read: true,
        deserialize: false,
        name: 'workflow_steps_create',
        openapi: new OpenApiOperation(
            tags: ['Workflows'],
            summary: 'Add a step to a workflow',
            description: 'Creates and appends a new WorkflowStep.',
            parameters: [
                new OpenApiParameter(name: 'id', in: 'path', description: 'Workflow id', required: true, schema: ['type' => 'integer']),
            ],
            requestBody: new RequestBody(
                required: true,
                content: new \ArrayObject([
                    'application/json' => new MediaType(schema: new \ArrayObject([
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'step_type' => ['type' => 'string', 'description' => 'One of WorkflowStepType\'s cases.'],
                            'order' => ['type' => 'integer'],
                            'configuration' => ['type' => 'object'],
                            'is_active' => ['type' => 'boolean', 'default' => true],
                        ],
                        'required' => ['name', 'step_type', 'order'],
                    ])),
                ]),
            ),
            responses: [
                '201' => new OpenApiResponse(description: 'The created step.', content: new \ArrayObject([
                    'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                ])),
                '400' => new OpenApiResponse(description: 'Invalid JSON body, or missing/invalid name, step_type, or order.'),
                '401' => new OpenApiResponse(description: 'Not authenticated.'),
                '403' => new OpenApiResponse(description: 'Authenticated but not ROLE_ADMIN.'),
                '404' => new OpenApiResponse(description: 'Workflow not found.'),
            ],
        ),
    ),
], security: "is_granted('ROLE_ADMIN')")]
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
