<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * One row per create/update/delete on an admin-managed resource (AiProviderConfig,
 * Faq, Workflow, ...). Append-only history, written by App\EventListener\
 * AuditLogListener -- not a Sylius resource itself (nothing to create/edit here),
 * read-only via App\Controller\Admin\AuditLogController, same pattern as
 * AnalyticsController/AnalyticsService.
 *
 * actorEmail is a snapshot (not a FK to User) so an entry stays readable after
 * the operator account that made the change is deleted -- User has full CRUD
 * (see config/routes/admin.yaml), so that's not a hypothetical.
 */
#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(name: 'audit_log_resource_idx', columns: ['resource_type', 'resource_id'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    public function __construct(
        #[ORM\Column(length: 20)]
        private string $action,
        #[ORM\Column(length: 60)]
        private string $resourceType,
        #[ORM\Column(length: 40, nullable: true)]
        private ?string $resourceId,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $resourceLabel,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $actorEmail,
    ) {
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    public function getResourceLabel(): ?string
    {
        return $this->resourceLabel;
    }

    public function getActorEmail(): ?string
    {
        return $this->actorEmail;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
