<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Conversation;
use App\Entity\User;
use App\Entity\WorkflowExecution;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Stamps Conversation::user / WorkflowExecution::triggeredBy with the
 * currently authenticated user on creation, if not already set. Both
 * firewalls (admin session login, api HTTP Basic) resolve to a real User
 * row now, so this covers conversations created through the admin
 * backoffice, the API, and workflow executions triggered as tool calls
 * during an authenticated chat request.
 */
#[AsDoctrineListener(event: Events::prePersist)]
final readonly class UserStampListener
{
    public function __construct(private Security $security) {}

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Conversation && !$entity->getUser() instanceof User) {
            $entity->setUser($this->currentUser());
        }

        if ($entity instanceof WorkflowExecution && !$entity->getTriggeredBy() instanceof User) {
            $entity->setTriggeredBy($this->currentUser());
        }
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
