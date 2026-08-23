<?php

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Faq;
use Doctrine\ORM\QueryBuilder;

/**
 * GET /api/faqs is public (see Faq::class) so the landing page/chat widget
 * can pull suggested questions without authenticating -- keep draft/retired
 * FAQs out of that response. The /admin/faqs grid still lists every row: it
 * queries through Sylius's own repository, not API Platform, so it never
 * goes through this extension.
 */
final class FaqActiveCollectionExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Faq::class !== $resourceClass) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder
            ->andWhere(sprintf('%s.isActive = :faqIsActive', $alias))
            ->setParameter('faqIsActive', true);
    }
}
