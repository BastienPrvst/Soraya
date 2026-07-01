<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
                'required' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez renseigner votre nom'
                    ),
                    new Length(
                        max: 255,
                        maxMessage: 'Le nom ne peut pas faire plus de 255 caractères'
                    ),
                ]
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
                'required' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez renseigner votre prénom'
                    ),
                    new Length(
                        max: 255,
                        maxMessage: 'Le prénom ne peut pas faire plus de 255 caractères'
                    )
                ]
            ])
            ->add('email_address', EmailType::class, [
                'label' => 'Email',
                'required' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez renseigner votre adresse email'
                    ),
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'required' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez renseigner votre message'
                    ),
                    new Length(
                        max: 5000,
                        maxMessage: 'Votre message ne peut pas dépasser 5000 caractères'
                    )
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer'
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
