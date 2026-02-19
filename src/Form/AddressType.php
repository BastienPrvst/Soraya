<?php

namespace App\Form;

use App\Entity\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Street1', TextType::class, ['label' => 'Rue'])
            ->add('Street2', TextType::class, ['label' => 'Rue 2', 'required' => false])
            ->add('City', TextType::class, ['label' => 'Ville'])
            ->add('Zipcode', IntegerType::class, ['label' => 'Code postal'])
            ->add('country', CountryType::class, [
                'label' => 'Pays',
                'preferred_choices' => ['FR'],
                'choice_filter' => static function (?string $countryCode): bool {
                    return in_array($countryCode, ['FR', 'BE', 'LU', 'MC', 'GB'], true);
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
        ]);
    }
}
