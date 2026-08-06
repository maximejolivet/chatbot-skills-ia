<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Controller\ConversationMessagesController;
use App\Controller\ConversationStreamController;
use App\Repository\ConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Resource\Model\ResourceInterface;

/**
 * A chat conversation between a user and the AI.
 *
 * NOTE: this backend has no auth/User system yet, so there is no `user`
 * field -- same gap as Document.uploaded_by, Workflow.created_by, etc. --
 * and conversations are globally readable/writable by anyone with an id,
 * rather than scoped per-user.
 */
#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
        new Get(
            uriTemplate: '/conversations/{id}/messages',
            controller: ConversationMessagesController::class,
            read: true,
            output: false,
            name: 'conversation_messages_list',
        ),
        new Post(
            uriTemplate: '/conversations/{id}/messages',
            controller: ConversationMessagesController::class,
            read: true,
            deserialize: false,
            output: false,
            name: 'conversation_messages_send',
        ),
        new Post(
            uriTemplate: '/conversations/{id}/stream',
            controller: ConversationStreamController::class,
            read: true,
            deserialize: false,
            output: false,
            name: 'conversation_stream',
        ),
    ],
)]
class Conversation implements ResourceInterface
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
