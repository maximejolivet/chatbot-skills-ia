<?php

namespace App\Form\Type;

use App\Form\DataTransformer\JsonToArrayTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A textarea editing a PHP array as pretty-printed JSON.
 */
final class JsonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new JsonToArrayTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['rows' => 6, 'class' => 'font-mono text-sm'],
        ]);
    }

    public function getParent(): string
    {
        return \Symfony\Component\Form\Extension\Core\Type\TextareaType::class;
    }
}
