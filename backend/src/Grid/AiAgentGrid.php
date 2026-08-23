<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\AiAgent;
use Sylius\Bundle\GridBundle\Builder\Action\CreateAction;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\Action\UpdateAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\MainActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

#[AsGrid(resourceClass: AiAgent::class, name: 'app_ai_agent')]
final class AiAgentGrid
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->orderBy('name', 'asc')
            ->withFields(
                StringField::create('name')->setLabel('Nom')->setSortable(true),
                StringField::create('workflows')->setLabel('Outils'),
                StringField::create('isActive')->setLabel('Actif'),
                StringField::create('updatedAt')->setLabel('Modifié le')->setSortable(true),
            )
            ->addActionGroup(MainActionGroup::create(CreateAction::create()))
            ->addActionGroup(ItemActionGroup::create(ShowAction::create(), UpdateAction::create(), DeleteAction::create()))
        ;
    }
}
