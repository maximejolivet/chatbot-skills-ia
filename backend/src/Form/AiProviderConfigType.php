<?php

namespace App\Form;

use App\Entity\AiProviderConfig;
use App\Enum\AiProvider;
use App\Enum\AiProviderUsage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AiProviderConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class)
            ->add('usage', EnumType::class, ['class' => AiProviderUsage::class])
            ->add('provider', EnumType::class, ['class' => AiProvider::class])
            ->add('model', TextType::class, ['required' => false])
            ->add('baseUrl', TextType::class, ['required' => false, 'label' => 'Base URL (Ollama)'])
            ->add('apiEndpoint', TextType::class, ['required' => false, 'label' => 'API endpoint (OpenAI-compatible)'])
            ->add('apiKey', TextType::class, ['required' => false, 'label' => 'API key'])
            ->add('isActive', CheckboxType::class, ['required' => false])
            ->add('isDefault', CheckboxType::class, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AiProviderConfig::class]);
    }
}
