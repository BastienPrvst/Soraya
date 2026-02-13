<?php

namespace App\Form;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder

            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('firstname', TextType::class, [
                'label' => 'Prénom',
            ])
            ->add('lastname', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('phoneNumber', TelType::class, [
                'label' => 'Téléphone'
            ])
            ->add(
                $builder->create('deliveryAddress', FormType::class, ['by_reference' => false])
                    ->add('City', TextType::class, [
                        'label' => 'Ville',
                        'required' => true,
                    ])
                    ->add('Zipcode', IntegerType::class, [
                        'label' => 'Code postal',
                    ])
                    ->add('Street1', TextType::class, [
                        'label' => 'Rue',
                    ])
                    ->add('Street2', TextType::class, [
                        'label' => 'Rue 2',
                    ])
                    ->add('country', CountryType::class, [
                        'label' => 'Pays',
                        'choices' => [
                            'Angleterre' => 'en',
                            'France' => 'fr',
                            'Belgique' => 'B',
                            'Luxembourg' => 'L',
                            'Monaco' => 'MO',
                        ],
                        'preferred_choices' => ['FR'],
                        'data' => 'FR',
                    ])
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
