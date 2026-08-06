<?php

namespace App\Grid;

use App\Entity\Message;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

#[AsGrid(resourceClass: Message::class, name: 'app_message')]
final class MessageGrid
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->orderBy('createdAt', 'desc')
            ->withFields(
                StringField::create('conversation')->setLabel('Conversation')->setPath('conversation.title'),
                StringField::create('role')->setLabel('Rôle'),
                StringField::create('content')->setLabel('Contenu'),
                StringField::create('createdAt')->setLabel('Le')->setSortable(true),
            )
            ->addActionGroup(ItemActionGroup::create(ShowAction::create()))
        ;
    }
}
