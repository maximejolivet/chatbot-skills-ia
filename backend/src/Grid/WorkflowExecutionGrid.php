<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\WorkflowExecution;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

#[AsGrid(resourceClass: WorkflowExecution::class, name: 'app_workflow_execution')]
final class WorkflowExecutionGrid
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->orderBy('createdAt', 'desc')
            ->withFields(
                StringField::create('workflow')->setLabel('Workflow')->setPath('workflow.name'),
                StringField::create('status')->setLabel('Statut'),
                StringField::create('startedAt')->setLabel('Démarrée le'),
                StringField::create('completedAt')->setLabel('Terminée le'),
                StringField::create('createdAt')->setLabel('Créée le')->setSortable(true),
            )
            ->addActionGroup(ItemActionGroup::create(ShowAction::create()))
        ;
    }
}
