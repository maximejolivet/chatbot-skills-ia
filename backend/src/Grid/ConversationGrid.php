<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\Conversation;
use Sylius\Bundle\GridBundle\Builder\Action\CreateAction;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\Action\UpdateAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\MainActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

#[AsGrid(resourceClass: Conversation::class, name: 'app_conversation')]
final class ConversationGrid
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->orderBy('updatedAt', 'desc')
            ->withFields(
                StringField::create('title')->setLabel('Titre')->setSortable(true),
                StringField::create('visitorFirstName')->setLabel('Prénom'),
                StringField::create('visitorLastName')->setLabel('Nom'),
                StringField::create('messageCount')->setLabel('Messages'),
                StringField::create('isActive')->setLabel('Actif'),
                StringField::create('updatedAt')->setLabel('Modifiée le')->setSortable(true),
            )
            ->addActionGroup(MainActionGroup::create(CreateAction::create()))
            ->addActionGroup(ItemActionGroup::create(ShowAction::create(), UpdateAction::create(), DeleteAction::create()))
        ;
    }
}
