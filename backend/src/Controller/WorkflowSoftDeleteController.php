<?php

namespace App\Controller;

use App\Entity\Workflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Soft delete: sets is_active=false rather than removing the row.
 */
#[AsController]
final readonly class WorkflowSoftDeleteController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    // See WorkflowStepsController's comment -- Workflow's resource-level
    // security doesn't apply to custom-controller operations.
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Workflow $data): Response
    {
        $data->setIsActive(false);
        $this->entityManager->flush();

        return new Response(null, 204);
    }
}
