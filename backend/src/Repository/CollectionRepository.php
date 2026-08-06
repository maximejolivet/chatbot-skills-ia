<?php

namespace App\Repository;

use App\Entity\Collection;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface as SyliusRepositoryInterface;

/**
 * @extends ServiceEntityRepository<Collection>
 */
class CollectionRepository extends ServiceEntityRepository implements SyliusRepositoryInterface
{
    use ResourceRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Collection::class);
    }

    public function findCommon(): ?Collection
    {
        return $this->findOneBy(['isCommon' => true]);
    }

    public function findOneByAgent(int $agentId): ?Collection
    {
        return $this->findOneBy(['agent' => $agentId]);
    }
}
