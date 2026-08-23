<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\AiAgent;
use App\Entity\Workflow;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AiAgentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('description', TextareaType::class, ['required' => false, 'empty_data' => ''])
            ->add('systemPrompt', TextareaType::class, ['attr' => ['rows' => 6], 'label' => 'Prompt système'])
            ->add('workflows', EntityType::class, [
                'class' => Workflow::class,
                'choice_label' => 'name',
                'multiple' => true,
                'required' => false,
                'help' => 'Outils (workflows) disponibles pour cet agent.',
            ])
            ->add('isActive', CheckboxType::class, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AiAgent::class]);
    }
}
