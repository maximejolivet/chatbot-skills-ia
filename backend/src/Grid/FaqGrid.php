<?php

namespace App\Grid;

use App\Entity\Faq;
use Sylius\Bundle\GridBundle\Builder\Action\CreateAction;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\Action\UpdateAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\MainActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

#[AsGrid(resourceClass: Faq::class, name: 'app_faq')]
final class FaqGrid
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->orderBy('updatedAt', 'desc')
            ->withFields(
                StringField::create('question')->setLabel('Question')->setSortable(true),
                StringField::create('category')->setLabel('Catégorie')->setPath('category.name'),
                StringField::create('isActive')->setLabel('Actif'),
                StringField::create('updatedAt')->setLabel('Modifiée le')->setSortable(true),
            )
            ->addActionGroup(MainActionGroup::create(CreateAction::create()))
            ->addActionGroup(ItemActionGroup::create(ShowAction::create(), UpdateAction::create(), DeleteAction::create()))
        ;
    }
}
