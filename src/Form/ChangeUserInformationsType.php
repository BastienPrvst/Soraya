<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ChangeUserInformationsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [new NotBlank(message: 'Le prénom est obligatoire.')],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire.')],
            ])

            ->add('address', AddressType::class, [
                'label' => 'Adresse de livraison favorite',
                'required' => false,
                'validation_groups' => false,
            ])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'autocomplete' => 'current-password',
                ],
            ])
            ->add('newPassword', PasswordType::class, [
                'label' => 'Nouveau mot de passe',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new Length(
                        min: 8,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'
                    ),
                    new Regex(
                        pattern: '/[A-Z]/',
                        message: 'Le mot de passe doit contenir au moins une majuscule.'
                    ),
                    new Regex(
                        pattern: '/[a-z]/',
                        message: 'Le mot de passe doit contenir au moins une minuscule.'
                    ),
                    new Regex(
                        pattern: '/\d/',
                        message: 'Le mot de passe doit contenir au moins un chiffre.'
                    ),
                    new Regex(
                        pattern: '/[#?!@$%^&*-]/',
                        message: 'Le mot de passe doit contenir au moins un caractère spécial.'
                    ),
                ],
            ])
            ->add('newPasswordConfirmation', PasswordType::class, [
                'label' => 'Confirmation',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
