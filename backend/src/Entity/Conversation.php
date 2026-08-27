<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
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
use App\Controller\ConversationMessagesController;
use App\Controller\ConversationSourcesController;
use App\Controller\ConversationStreamController;
use App\Controller\MessageFeedbackController;
use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * A chat conversation between a user and the AI.
 *
 * `user` is the operator account that owns this conversation, stamped
 * automatically on creation (see UserStampListener) from whoever is
 * authenticated on the admin/api firewall. Null for conversations created
 * before multi-user auth existed (admin-only, see OwnershipVoter). ROLE_ADMIN
 * accounts can still read/write any conversation; ROLE_USER accounts are
 * restricted to their own via the `security` expressions below
 * (OwnershipVoter) plus OwnershipCollectionExtension for GetCollection.
 */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(security: "is_granted('OWNER', object)"),
        new Post(),
        new Patch(security: "is_granted('OWNER', object)"),
        new Delete(security: "is_granted('OWNER', object)"),
        // No `security:` here -- empirically not enforced for
        // custom-controller (read: true + controller:) operations in this
        // API Platform version. Ownership is checked instead via
        // #[IsGranted('OWNER', subject: 'data')] on each controller itself
        // (ConversationMessagesController, ConversationStreamController),
        // which Symfony's IsGrantedAttributeListener always enforces.
        new Get(
            uriTemplate: '/conversations/{id}/messages',
            controller: ConversationMessagesController::class,
            output: false,
            read: true,
            name: 'conversation_messages_list',
            openapi: new OpenApiOperation(
                tags: ['Conversations'],
                summary: 'List the messages of a conversation',
                description: 'Returns every message of the conversation, ordered by creation date.',
                parameters: [
                    new OpenApiParameter(name: 'id', in: 'path', description: 'Conversation id', required: true, schema: ['type' => 'integer']),
                ],
                responses: [
                    '200' => new OpenApiResponse(description: 'Messages of the conversation.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'array', 'items' => ['type' => 'object']])),
                    ])),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '403' => new OpenApiResponse(description: 'Authenticated but not the owner of this conversation.'),
                    '404' => new OpenApiResponse(description: 'Conversation not found.'),
                ],
            ),
        ),
        new Post(
            uriTemplate: '/conversations/{id}/messages',
            controller: ConversationMessagesController::class,
            output: false,
            read: true,
            deserialize: false,
            name: 'conversation_messages_send',
            openapi: new OpenApiOperation(
                tags: ['Conversations'],
                summary: 'Send a user message and get the assistant reply',
                description: 'Persists the user message, runs the chat pipeline (RAG + tool-calling as configured on the agent) and persists+returns the assistant reply. Rate-limited per client IP.',
                parameters: [
                    new OpenApiParameter(name: 'id', in: 'path', description: 'Conversation id', required: true, schema: ['type' => 'integer']),
                ],
                requestBody: new RequestBody(
                    description: 'The user message to send.',
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'description' => 'The user message text.'],
                                'agent_id' => ['type' => 'integer', 'nullable' => true, 'description' => 'AI agent to use; defaults to the active agent when omitted.'],
                            ],
                            'required' => ['message'],
                        ])),
                    ]),
                ),
                responses: [
                    '201' => new OpenApiResponse(description: 'The persisted assistant message.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                    ])),
                    '400' => new OpenApiResponse(description: 'Missing message.'),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '403' => new OpenApiResponse(description: 'Authenticated but not the owner of this conversation.'),
                    '404' => new OpenApiResponse(description: 'Conversation not found.'),
                    '429' => new OpenApiResponse(description: 'Too many messages sent too quickly; slow down.'),
                ],
            ),
        ),
        new Post(
            uriTemplate: '/conversations/{id}/stream',
            controller: ConversationStreamController::class,
            output: false,
            read: true,
            deserialize: false,
            name: 'conversation_stream',
            openapi: new OpenApiOperation(
                tags: ['Conversations'],
                summary: 'Send a user message and stream the assistant reply',
                description: 'Same as conversation_messages_send, but streams the reply as Server-Sent Events (event types: user_message, delta, tool_call, ai_complete, error, done) instead of waiting for the full completion.',
                parameters: [
                    new OpenApiParameter(name: 'id', in: 'path', description: 'Conversation id', required: true, schema: ['type' => 'integer']),
                ],
                requestBody: new RequestBody(
                    description: 'The user message to send.',
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string', 'description' => 'The user message text.'],
                                'agent_id' => ['type' => 'integer', 'nullable' => true, 'description' => 'AI agent to use; defaults to the active agent when omitted.'],
                            ],
                            'required' => ['message'],
                        ])),
                    ]),
                ),
                responses: [
                    '200' => new OpenApiResponse(description: 'A text/event-stream of SSE frames.', content: new \ArrayObject([
                        'text/event-stream' => new MediaType(schema: new \ArrayObject(['type' => 'string'])),
                    ])),
                    '400' => new OpenApiResponse(description: 'Missing message.'),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '403' => new OpenApiResponse(description: 'Authenticated but not the owner of this conversation.'),
                    '404' => new OpenApiResponse(description: 'Conversation not found.'),
                    '429' => new OpenApiResponse(description: 'Too many messages sent too quickly; slow down.'),
                ],
            ),
        ),
        new Get(
            uriTemplate: '/conversations/{id}/sources',
            controller: ConversationSourcesController::class,
            output: false,
            read: true,
            name: 'conversation_sources',
            openapi: new OpenApiOperation(
                tags: ['Conversations'],
                summary: 'List the RAG sources cited across a conversation',
                description: 'Extracts the `sources` metadata recorded on every assistant message of the conversation (document id/title, relevance score).',
                parameters: [
                    new OpenApiParameter(name: 'id', in: 'path', description: 'Conversation id', required: true, schema: ['type' => 'integer']),
                ],
                responses: [
                    '200' => new OpenApiResponse(description: 'Aggregated sources for the conversation.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                    ])),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '403' => new OpenApiResponse(description: 'Authenticated but not the owner of this conversation.'),
                    '404' => new OpenApiResponse(description: 'Conversation not found.'),
                ],
            ),
        ),
        new Patch(
            uriTemplate: '/conversations/{id}/messages/{messageId}/feedback',
            controller: MessageFeedbackController::class,
            output: false,
            read: true,
            deserialize: false,
            name: 'conversation_message_feedback',
            openapi: new OpenApiOperation(
                tags: ['Conversations'],
                summary: 'Set or clear the thumbs up/down feedback on a message',
                description: 'Sets feedback to "positive", "negative", or clears it with null. The message must belong to the given conversation.',
                parameters: [
                    new OpenApiParameter(name: 'id', in: 'path', description: 'Conversation id', required: true, schema: ['type' => 'integer']),
                    new OpenApiParameter(name: 'messageId', in: 'path', description: 'Message id', required: true, schema: ['type' => 'integer']),
                ],
                requestBody: new RequestBody(
                    required: true,
                    content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject([
                            'type' => 'object',
                            'properties' => [
                                'feedback' => ['type' => 'string', 'enum' => ['positive', 'negative', null], 'nullable' => true],
                            ],
                            'required' => ['feedback'],
                        ])),
                    ]),
                ),
                responses: [
                    '200' => new OpenApiResponse(description: 'The updated message.', content: new \ArrayObject([
                        'application/json' => new MediaType(schema: new \ArrayObject(['type' => 'object'])),
                    ])),
                    '400' => new OpenApiResponse(description: 'Missing feedback, or invalid feedback value.'),
                    '401' => new OpenApiResponse(description: 'Not authenticated.'),
                    '403' => new OpenApiResponse(description: 'Authenticated but not the owner of this conversation.'),
                    '404' => new OpenApiResponse(description: 'Conversation, or message not found in this conversation.'),
                ],
            ),
        ),
    ],
)]
class Conversation implements ResourceInterface, OwnedResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $title = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column]
    private bool $isActive = true;

    // Set internally by the "set_conversation" workflow step (App\Workflow\
    // WorkflowExecutionService::handleSetConversation) once the agent has
    // asked for it in-chat -- never written directly by an API client.
    #[ApiProperty(writable: false)]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $visitorFirstName = null;

    #[ApiProperty(writable: false)]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $visitorLastName = null;

    // Not readable/writable over the API: User has no serialization groups
    // configured, so embedding it here would otherwise leak User::password
    // (the hash) in every Conversation response. Visible in the admin
    // backoffice (Twig/PropertyAccessor, not the API serializer).
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    /**
     * @var DoctrineCollection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'conversation', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private DoctrineCollection $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
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

    public function getVisitorFirstName(): ?string
    {
        return $this->visitorFirstName;
    }

    public function setVisitorFirstName(?string $visitorFirstName): static
    {
        $this->visitorFirstName = $visitorFirstName;

        return $this;
    }

    public function getVisitorLastName(): ?string
    {
        return $this->visitorLastName;
    }

    public function setVisitorLastName(?string $visitorLastName): static
    {
        $this->visitorLastName = $visitorLastName;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    // Not readable over the API -- same reason as $user above (getters
    // matching getXxx() are auto-discovered as virtual API properties by
    // API Platform unless explicitly excluded; without this, getOwnerUser()
    // would embed the full User, including the password hash).
    #[ApiProperty(readable: false, writable: false)]
    public function getOwnerUser(): ?User
    {
        return $this->user;
    }

    #[ApiProperty(readable: false, writable: false)]
    public static function getOwnerFieldName(): string
    {
        return 'user';
    }

    /**
     * @return DoctrineCollection<int, Message>
     */
    public function getMessages(): DoctrineCollection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }

        return $this;
    }

    public function getMessageCount(): int
    {
        return $this->messages->count();
    }
}
