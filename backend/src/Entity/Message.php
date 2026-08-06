<?php

namespace App\Entity;

use App\Enum\MessageRole;
use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * A single message in a conversation. Not exposed as its own API Platform
 * resource -- surfaced through GET/POST /conversations/{id}/messages (no
 * direct CRUD for Message). Also read-only in the admin backoffice.
 */
#[ORM\Entity(repositoryClass: MessageRepository::class)]
class Message implements ResourceInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\Column(length: 10, enumType: MessageRole::class)]
    private MessageRole $role;

    #[ORM\Column(type: 'text')]
    private string $content;

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

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function setConversation(Conversation $conversation): static
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function getRole(): MessageRole
    {
        return $this->role;
    }

    public function setRole(MessageRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

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
