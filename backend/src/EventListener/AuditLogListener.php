<?php

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Records every create/update/delete made through an admin Sylius resource
 * (AiProviderConfig, Faq, Workflow, User, ...) into `audit_log`, for
 * traceability (docs/BACKLOG.md "Journal d'audit des actions admin").
 *
 * Hooks the generic app.<resource>.{post_create,post_update,pre_delete}
 * events that Sylius\Bundle\ResourceBundle\Controller\ResourceController
 * already dispatches for every resource (see vendor
 * .../Controller/EventDispatcher.php) instead of a bespoke listener per
 * entity or per Grid. RESOURCE_TYPES lists exactly the app.* aliases from
 * config/packages/sylius_resource.yaml that actually expose create/update/
 * delete in config/routes/admin.yaml -- subscribing to an action a resource
 * doesn't expose (e.g. document has no create route) is harmless, the event
 * simply never fires.
 *
 * Delete is captured on *pre*_delete rather than post_delete: Doctrine resets
 * an auto-generated identifier to null on the in-memory entity right after
 * UnitOfWork::executeDeletions() removes the row, so by post_delete
 * getId() would already read back null. The tradeoff is the mirror image of
 * that same risk: if some other pre_delete listener stops the event (none do
 * today, see class docblock), this would log a deletion that didn't actually
 * happen.
 */
final readonly class AuditLogListener implements EventSubscriberInterface
{
    private const array RESOURCE_TYPES = [
        'ai_provider_config',
        'vector_index',
        'document_category',
        'faq',
        'collection',
        'document',
        'workflow',
        'ai_agent',
        'conversation',
        'user',
    ];

    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        $events = [];
        foreach (self::RESOURCE_TYPES as $resourceType) {
            $events["app.{$resourceType}.post_create"] = 'onResourceChanged';
            $events["app.{$resourceType}.post_update"] = 'onResourceChanged';
            $events["app.{$resourceType}.pre_delete"] = 'onResourceChanged';
        }

        return $events;
    }

    public function onResourceChanged(ResourceControllerEvent $event, string $eventName): void
    {
        // "app.<resource_type>.post_<action>" or "app.<resource_type>.pre_delete"
        [, $resourceType, $rawAction] = explode('.', $eventName, 3);
        $action = preg_replace('/^(post_|pre_)/', '', $rawAction) ?? $rawAction;

        $subject = $event->getSubject();
        if (!\is_object($subject)) {
            return;
        }

        $entry = new AuditLog(
            action: $action,
            resourceType: $resourceType,
            resourceId: $this->readId($subject),
            resourceLabel: $this->readLabel($subject),
            actorEmail: $this->currentActorEmail(),
        );

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }

    private function readId(object $subject): ?string
    {
        if (!$this->propertyAccessor->isReadable($subject, 'id')) {
            return null;
        }

        $id = $this->propertyAccessor->getValue($subject, 'id');

        return \is_scalar($id) ? (string) $id : null;
    }

    private function readLabel(object $subject): ?string
    {
        // Same best-effort fallback chain as AdminExtension::formatObject().
        foreach (['getName', 'getTitle', 'getQuestion', 'getEmail', '__toString'] as $method) {
            if (method_exists($subject, $method)) {
                return (string) $subject->{$method}();
            }
        }

        return null;
    }

    private function currentActorEmail(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getEmail() : null;
    }
}
