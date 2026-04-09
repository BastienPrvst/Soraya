<?php

namespace App\Form;

use App\Entity\Order;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

class RelayOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder

            ->add('email', EmailType::class, [
                'label' => 'Email *',
                'required' => false,
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom *',
                'required' => false,
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom *',
                'required' => false,
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Téléphone *',
                'required' => false,
            ])
            ->add('relay_id', HiddenType::class, [
                'required' => true,
                'error_bubbling' => false,
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez choisir un point relais valide.'
                    )
                ]
            ])
            ->add('CGU', CheckboxType::class, [
                'mapped' => false,
                'label' => 'Je certifie avoir lu les Conditions Générales d\'utilisation.',
                'required' => true,
                'data' => $options['CGU'],
                'constraints' => [
                    new IsTrue(
                        message: 'Veuillez accepter les conditions.'
                    )
                ]
            ])
            ->add('submit_relay', SubmitType::class, [
                'label' => 'Valider',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
            'CGU' => false
        ]);
    }
}
