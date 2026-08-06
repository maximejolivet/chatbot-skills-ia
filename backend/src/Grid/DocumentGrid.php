<?php

namespace App\Grid;

use App\Entity\Document;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\Action\ShowAction;
use Sylius\Bundle\GridBundle\Builder\Action\UpdateAction;
use Sylius\Bundle\GridBundle\Builder\ActionGroup\ItemActionGroup;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;

/**
 * No "create" action -- documents are uploaded via POST /api/documents
 * (multipart), not through this generic admin form. See DocumentType.
 */
#[AsGrid(resourceClass: Document::class, name: 'app_document')]
final class DocumentGrid
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder
            ->orderBy('uploadedAt', 'desc')
            ->withFields(
                StringField::create('title')->setLabel('Titre')->setSortable(true),
                StringField::create('fileType')->setLabel('Type'),
                StringField::create('category')->setLabel('Catégorie')->setPath('category.name'),
                StringField::create('status')->setLabel('Statut'),
                StringField::create('chunkCount')->setLabel('Chunks'),
                StringField::create('uploadedAt')->setLabel('Envoyé le')->setSortable(true),
            )
            ->addActionGroup(ItemActionGroup::create(ShowAction::create(), UpdateAction::create(), DeleteAction::create()))
        ;
    }
}
