<?php

namespace App\Controller;

use App\Entity\Workflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Soft delete: sets is_active=false rather than removing the row.
 */
#[AsController]
final class WorkflowSoftDeleteController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(Workflow $data): Response
    {
        $data->setIsActive(false);
        $this->entityManager->flush();

        return new Response(null, 204);
    }
}
