<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\DocumentCategory;
use App\Entity\Faq;
use App\Form\Type\CommaListType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FaqType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('question', TextType::class)
            ->add('answer', TextareaType::class, ['attr' => ['rows' => 5]])
            ->add('category', EntityType::class, [
                'class' => DocumentCategory::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '—',
            ])
            ->add('tags', CommaListType::class, ['required' => false, 'help' => 'Séparés par des virgules'])
            ->add('isActive', CheckboxType::class, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Faq::class]);
    }
}
