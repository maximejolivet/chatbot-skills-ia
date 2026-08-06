<?php

namespace App\Repository;

use App\Entity\AiAgent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface as SyliusRepositoryInterface;

/**
 * @extends ServiceEntityRepository<AiAgent>
 */
class AiAgentRepository extends ServiceEntityRepository implements SyliusRepositoryInterface
{
    use ResourceRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiAgent::class);
    }

    public function getActive(int $id): ?AiAgent
    {
        return $this->findOneBy(['id' => $id, 'isActive' => true]);
    }

    /**
     * @return AiAgent[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = true')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
