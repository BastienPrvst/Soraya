<?php

declare(strict_types=1);

namespace App\Controller\Admin\Fields;

use App\Entity\Address;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderAddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('street1', TextType::class, ['label' => 'Rue 1'])
            ->add('street2', TextType::class, ['label' => 'Rue 2'])
            ->add('city', TextType::class,   ['label' => 'Ville'])
            ->add('zipCode', TextType::class, ['label' => 'Code postal'])
            ->add('country', TextType::class, ['label' => 'Pays']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Address::class,
        ]);
        $resolver->setDefined(['class', 'query_builder']);
        $resolver->setAllowedTypes('class', ['null', 'string']);
        $resolver->setAllowedTypes('query_builder', ['null', 'array', 'callable', QueryBuilder::class]);
    }

}
