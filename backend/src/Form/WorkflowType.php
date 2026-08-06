<?php

namespace App\Form;

use App\Entity\Workflow;
use App\Enum\WorkflowStatus;
use App\Enum\WorkflowTriggerType;
use App\Form\Type\JsonType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WorkflowType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['help' => "Aussi utilisé comme nom d'outil pour le LLM."])
            ->add('description', TextareaType::class, ['required' => false, 'empty_data' => ''])
            ->add('triggerType', EnumType::class, ['class' => WorkflowTriggerType::class])
            ->add('status', EnumType::class, ['class' => WorkflowStatus::class])
            ->add('triggerConfig', JsonType::class, ['required' => false])
            ->add('parametersSchema', JsonType::class, [
                'required' => false,
                'label' => 'Schéma des paramètres (JSON Schema)',
                'help' => "Définition exposée au LLM comme paramètres de l'outil.",
            ])
            ->add('isActive', CheckboxType::class, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Workflow::class]);
    }
}
