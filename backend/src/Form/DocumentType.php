<?php

namespace App\Form;

use App\Entity\Collection;
use App\Entity\Document;
use App\Entity\DocumentCategory;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Edits only the metadata fields -- the file itself is uploaded via
 * POST /api/documents (multipart), not through this generic admin form.
 */
final class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('description', TextareaType::class, ['required' => false, 'empty_data' => ''])
            ->add('category', EntityType::class, [
                'class' => DocumentCategory::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '—',
            ])
            ->add('collection', EntityType::class, [
                'class' => Collection::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '—',
                'help' => 'Détermine la collection Qdrant utilisée à la prochaine (ré)indexation.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Document::class]);
    }
}
