<?php

declare(strict_types=1);

namespace App\Grid;

use App\Entity\AiProviderConfig;
use Sylius\Bundle\GridBundle\Builder\Action\CreateAction;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\Action\UpdateAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\MainActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

#[AsGrid(resourceClass: AiProviderConfig::class, name: 'app_ai_provider_config')]
final class AiProviderConfigGrid
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->orderBy('name', 'asc')
            ->withFields(
                StringField::create('name')->setLabel('Nom')->setSortable(true),
                StringField::create('usage')->setLabel('Usage')->setSortable(true),
                StringField::create('provider')->setLabel('Provider')->setSortable(true),
                StringField::create('model')->setLabel('Modèle'),
                StringField::create('isActive')->setLabel('Actif'),
                StringField::create('isDefault')->setLabel('Défaut'),
                StringField::create('lastTestStatus')->setLabel('Dernier test'),
            )
            ->addActionGroup(MainActionGroup::create(CreateAction::create()))
            ->addActionGroup(ItemActionGroup::create(ShowAction::create(), UpdateAction::create(), DeleteAction::create()))
        ;
    }
}
