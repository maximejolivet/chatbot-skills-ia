<?php

namespace App\Form;

use App\Entity\AiAgent;
use App\Entity\Collection;
use App\Entity\VectorIndex;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CollectionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('description', TextareaType::class, ['required' => false, 'empty_data' => ''])
            ->add('agent', EntityType::class, [
                'class' => AiAgent::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '—',
            ])
            ->add('vectorIndex', EntityType::class, [
                'class' => VectorIndex::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => '—',
                'label' => 'Index vectoriel',
            ])
            ->add('isCommon', CheckboxType::class, ['required' => false, 'label' => 'Collection commune'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Collection::class]);
    }
}
