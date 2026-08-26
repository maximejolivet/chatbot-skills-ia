<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\WorkflowExecution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface as SyliusRepositoryInterface;

/**
 * @extends ServiceEntityRepository<WorkflowExecution>
 *
 * @implements SyliusRepositoryInterface<WorkflowExecution>
 */
class WorkflowExecutionRepository extends ServiceEntityRepository implements SyliusRepositoryInterface
{
    use ResourceRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkflowExecution::class);
    }
}
