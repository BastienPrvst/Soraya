<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options'  => [
                    'label' => 'Mot de passe',
                    'constraints' => [
                        new NotBlank(
                            message : 'Veuillez saisir un mot de passe.'
                        ),
                        new Length(
                            min : 12,
                            minMessage : 'Le mot de passe doit contenir au moins {{ limit }} caractères.'
                        ),
                        new Regex(
                            pattern : '/[A-Z]/',
                            message : 'Le mot de passe doit contenir au moins une majuscule.',
                            match: true
                        ),
                        new Regex(
                            pattern : '/[a-z]/',
                            message : 'Le mot de passe doit contenir au moins une minuscule.',
                            match: true
                        ),
                        new Regex(
                            pattern : '/\d/',
                            message : 'Le mot de passe doit contenir au moins un chiffre.',
                            match: true
                        ),
                        new Regex(
                            pattern : '/[#?!@$%^&*-]/',
                            message : 'Le mot de passe doit contenir au moins un caractère spécial.',
                            match: true
                        ),
                    ]
                ],
                'second_options' => ['label' => 'Confirmation mot de passe'],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
