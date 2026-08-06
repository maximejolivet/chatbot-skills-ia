<?php

namespace App\Grid;

use App\Entity\VectorIndex;
use Sylius\Bundle\GridBundle\Builder\Action\CreateAction;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\Action\UpdateAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\MainActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

#[AsGrid(resourceClass: VectorIndex::class, name: 'app_vector_index')]
final class VectorIndexGrid
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->orderBy('createdAt', 'desc')
            ->withFields(
                StringField::create('name')->setLabel('Nom')->setSortable(true),
                StringField::create('collectionId')->setLabel('Collection Qdrant'),
                StringField::create('dimension')->setLabel('Dimension'),
                StringField::create('isActive')->setLabel('Actif'),
                StringField::create('createdAt')->setLabel('Créé le')->setSortable(true),
            )
            ->addActionGroup(MainActionGroup::create(CreateAction::create()))
            ->addActionGroup(ItemActionGroup::create(ShowAction::create(), UpdateAction::create(), DeleteAction::create()))
        ;
    }
}
