<?php

namespace App\Form;

use App\Entity\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('street1', TextType::class, [
                'label' => 'Rue *',
                'required' => false,
                'attr' => [
                    'class' => 'street_input'
                ]
            ])
            ->add('street2', TextType::class, [
                'label' => 'Rue 2',
                'required' => false,
                'attr' => [
                    'class' => 'street2_input'
                ]

            ])
            ->add('city', TextType::class, [
                'label' => 'Ville *',
                'required' => false,
                'attr' => [
                    'class' => 'city_input'
                ]
            ])
            ->add('zipcode', TextType::class, [
                'label' => 'Code postal *',
                'required' => false,
                'attr' => [
                    'class' => 'zipcode_input'
                ]
            ])
            ->add('country', CountryType::class, [
                'label' => 'Pays',
                'preferred_choices' => ['FR'],
                'choice_filter' => static function (?string $countryCode): bool {
                    return $countryCode === 'FR';
                },
            ]);

        if ($options['create'] === true) {
            $builder->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
            'create' => false
        ]);
    }
}
