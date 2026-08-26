<?php

namespace App\Repository;

use App\Entity\AiProviderConfig;
use App\Enum\AiProviderUsage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface as SyliusRepositoryInterface;

/**
 * @extends ServiceEntityRepository<AiProviderConfig>
 * @implements SyliusRepositoryInterface<AiProviderConfig>
 */
class AiProviderConfigRepository extends ServiceEntityRepository implements SyliusRepositoryInterface
{
    use ResourceRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiProviderConfig::class);
    }

    /**
     * Returns the highest-priority active config for a usage ('chat' or 'embedding'), or null.
     */
    public function getActiveForUsage(AiProviderUsage $usage): ?AiProviderConfig
    {
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.usage = :usage')
            ->andWhere('c.isActive = true')
            ->setParameter('usage', $usage)
            ->orderBy('c.isDefault', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof AiProviderConfig ? $result : null;
    }

    /**
     * All active configs for a usage, highest-priority first (same ordering as
     * getActiveForUsage) -- used to build a fallback chain instead of a single client.
     *
     * @return AiProviderConfig[]
     */
    public function getAllActiveForUsage(AiProviderUsage $usage): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.usage = :usage')
            ->andWhere('c.isActive = true')
            ->setParameter('usage', $usage)
            ->orderBy('c.isDefault', 'DESC')
            ->addOrderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
