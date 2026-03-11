<?php

namespace App\Form;

use App\Entity\Address;
use App\Entity\Order;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder

            ->add('email', EmailType::class, [
                'label' => 'Email *',
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom *',
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom *',
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Téléphone *',
            ])
            ->add('delivery_mode', HiddenType::class, [
                'mapped' => false,
                'data' => $options['delivery_mode'],
            ]);

        if ($options['delivery_mode'] === 'home') {
            $builder
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
            ->add('submit_home', SubmitType::class, [
                'label' => 'Valider',
            ]);
        } elseif ($options['delivery_mode'] === 'relay') {
            $builder
                ->add('relay_id', HiddenType::class, [
                    'required' => 'true',
                    "empty_data" => '',
                ])
                ->add('submit_relay', SubmitType::class, [
                    'label' => 'Valider',
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
            'delivery_mode' => 'home'
        ]);
    }
}
