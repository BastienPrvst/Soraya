<?php

namespace App\Form;

use App\Entity\Address;
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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints as Assert;

class DeliveryOrderType extends AbstractType
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
            ->add('delivery_mode', HiddenType::class, [
                'mapped' => false,
                'data' => 'home',
            ])
            ->add(
                $builder->create(
                    'deliveryAddress',
                    AddressType::class,
                    [
                        'label' => false,
                        'data_class' => Address::class,
                    ]
                )
            )
            ->add('billingAddress', CheckboxType::class, [
                'data' => true,
                'label' => 'Utiliser cette adresse comme adresse de facturation',
                'mapped' => false,
                'required' => false,
            ])
            ->add('CGU', CheckboxType::class, [
                'mapped' => false,
                'label' => 'Je certifie avoir lu les Conditions Générales d\'utilisation.',
                'required' => false,
                'data' => $options['CGU'],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez accepter les conditions pour continuer.'
                    )
                ]
            ])
            ->add('submit_home', SubmitType::class, [
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
