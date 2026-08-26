<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * `plainPassword` is unmapped: it never touches User::password directly. On
 * submit we hash it ourselves and only overwrite User::password when a value
 * was actually typed, so editing a user without retyping a password leaves
 * their existing hash untouched. It's required only when the user doesn't
 * have a password yet (i.e. this is a brand new account) -- there's no
 * separate "create" vs "edit" signal available from Sylius's generic form
 * builder, so User::password being empty is what stands in for "new".
 */
final class UserType extends AbstractType
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class)
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'help' => 'Laisser vide pour conserver le mot de passe actuel',
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => ['Admin' => 'ROLE_ADMIN'],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('isActive', CheckboxType::class, ['required' => false])
        ;

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            /** @var User $user */
            $user = $event->getData();
            $plainPassword = $event->getForm()->get('plainPassword')->getData();

            if (!\is_string($plainPassword) || '' === $plainPassword) {
                if ('' === $user->getPassword()) {
                    $event->getForm()->get('plainPassword')->addError(new FormError('Mot de passe requis pour un nouveau compte.'));
                }

                return;
            }

            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
