<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\OwnedResourceInterface;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Collection-level counterpart to OwnershipVoter: GetCollection has no single
 * `object` for a Voter to check, so non-admin users are restricted to their
 * own rows by filtering the query itself instead.
 */
final class OwnershipCollectionExtension implements QueryCollectionExtensionInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!is_a($resourceClass, OwnedResourceInterface::class, true)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $resourceClass::getOwnerFieldName();
        $parameter = $queryNameGenerator->generateParameterName($field);

        $queryBuilder
            ->andWhere(sprintf('%s.%s = :%s', $alias, $field, $parameter))
            ->setParameter($parameter, $user);
    }
}
