<?php

namespace App\Form;

use App\Entity\User;
use Doctrine\DBAL\Types\StringType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $cguUrl = $this->urlGenerator->generate('app_main');

        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options'  => [
                    'label' => 'Mot de passe',
                    'mapped' => false,
                    'help' =>
                        '<p class="text-[var(--dark)]">
                        Le mot de passe doit contenir : <br>
                         <ul class="text-[var(--dark)]">
                            <li class="text-[var(--dark)]">8 caractères minimum</li>
                            <li class="text-[var(--dark)]">Une majuscule et une minuscule</li>
                            <li class="text-[var(--dark)]">Un chiffre</li>
                            <li class="text-[var(--dark)]">Un caractère spécial</li>
                         </ul>
                     </p>
                    ',
                    'help_html' => true,
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'data-password-visibility-target' => 'input',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmation mot de passe',
                    'mapped' => false
                    ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => [
                    new NotBlank(
                        message : 'Veuillez saisir un mot de passe.'
                    ),
                    new Length(
                        min : 8,
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
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Prénom',
            ])
            ->add('birthday', BirthdayType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('cgu', CheckboxType::class, [
                'label' => sprintf(
                    'J\'accepte les <a href="%s" target="_blank"
                    rel="noopener" class="underline">conditions générales de vente et la politique de confidentialité</a>.',
                    $cguUrl
                ),
                'label_html' => true,
                'required' => true,
                'mapped' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Créer mon compte',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
