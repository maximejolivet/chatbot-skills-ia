<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Workflow;
use Sylius\Bundle\GridBundle\Builder\Action\CreateAction;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\Action\UpdateAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\MainActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

#[AsGrid(resourceClass: Workflow::class, name: 'app_workflow')]
final class WorkflowGrid
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->orderBy('updatedAt', 'desc')
            ->withFields(
                StringField::create('name')->setLabel('Nom')->setSortable(true),
                StringField::create('triggerType')->setLabel('Déclencheur'),
                StringField::create('status')->setLabel('Statut')->setSortable(true),
                StringField::create('isActive')->setLabel('Actif'),
                StringField::create('steps')->setLabel('Étapes'),
                StringField::create('executionCount')->setLabel('Exécutions'),
                StringField::create('updatedAt')->setLabel('Modifié le')->setSortable(true),
            )
            ->addActionGroup(MainActionGroup::create(CreateAction::create()))
            ->addActionGroup(ItemActionGroup::create(ShowAction::create(), UpdateAction::create(), DeleteAction::create()))
        ;
    }
}
