<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Collection;
use Sylius\Bundle\GridBundle\Builder\Action\CreateAction;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\Action\UpdateAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\MainActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

#[AsGrid(resourceClass: Collection::class, name: 'app_collection')]
final class CollectionGrid
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->orderBy('name', 'asc')
            ->withFields(
                StringField::create('name')->setLabel('Nom')->setSortable(true),
                StringField::create('agent')->setLabel('Agent')->setPath('agent.name'),
                StringField::create('vectorIndex')->setLabel('Index vectoriel')->setPath('vectorIndex.name'),
                StringField::create('isCommon')->setLabel('Commune'),
                StringField::create('createdAt')->setLabel('Créée le')->setSortable(true),
            )
            ->addActionGroup(MainActionGroup::create(CreateAction::create()))
            ->addActionGroup(ItemActionGroup::create(ShowAction::create(), UpdateAction::create(), DeleteAction::create()))
        ;
    }
}
