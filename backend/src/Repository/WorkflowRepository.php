<?php

namespace App\Repository;

use App\Entity\Workflow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface as SyliusRepositoryInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * @extends ServiceEntityRepository<Workflow>
 * @implements SyliusRepositoryInterface<Workflow>
 */
class WorkflowRepository extends ServiceEntityRepository implements SyliusRepositoryInterface
{
    use ResourceRepositoryTrait {
        remove as private removeResource;
    }

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workflow::class);
    }

    public function getActive(int $id): ?Workflow
    {
        return $this->findOneBy(['id' => $id, 'isActive' => true]);
    }

    /**
     * Soft delete: sets is_active=false rather than removing the row, same as
     * WorkflowSoftDeleteController (the API's own delete action).
     */
    public function remove(ResourceInterface $resource): void
    {
        \assert($resource instanceof Workflow);

        $resource->setIsActive(false);
        $this->getEntityManager()->flush();
    }
}
