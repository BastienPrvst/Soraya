<?php

namespace App\Form;

use App\Entity\Address;
use App\Entity\User;
use Doctrine\DBAL\Types\StringType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
            ])
            ->add('CGU', CheckboxType::class, [
                'label' => 'En cochant cette case, j\'accepte les termes et conditions d\'utilisation',
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
        ]);
    }
}
