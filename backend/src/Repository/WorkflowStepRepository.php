<?php

namespace App\Repository;

use App\Entity\WorkflowStep;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkflowStep>
 */
class WorkflowStepRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkflowStep::class);
    }

    /**
     * @return WorkflowStep[]
     */
    public function findActiveOrdered(int $workflowId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.workflow = :workflowId')
            ->andWhere('s.isActive = true')
            ->setParameter('workflowId', $workflowId)
            ->orderBy('s.order', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
