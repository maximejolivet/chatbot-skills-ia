<?php

namespace App\Grid;

use App\Entity\SearchQuery;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

/**
 * Read-only, analytics log -- see config/routes/admin.yaml (only: [index, show]).
 */
#[AsGrid(resourceClass: SearchQuery::class, name: 'app_search_query')]
final class SearchQueryGrid
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->orderBy('createdAt', 'desc')
            ->withFields(
                StringField::create('query')->setLabel('Requête'),
                StringField::create('vectorIndex')->setLabel('Index')->setPath('vectorIndex.name'),
                StringField::create('resultsCount')->setLabel('Résultats'),
                StringField::create('executionTime')->setLabel('Durée (s)'),
                StringField::create('createdAt')->setLabel('Le')->setSortable(true),
            )
            ->addActionGroup(ItemActionGroup::create(ShowAction::create()))
        ;
    }
}
