<?php

namespace App\Form\Type;

use App\Form\DataTransformer\CommaListToArrayTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A text input editing a PHP array of strings as a comma-separated list.
 */
final class CommaListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CommaListToArrayTransformer());
    }

    public function getParent(): string
    {
        return TextType::class;
    }
}
