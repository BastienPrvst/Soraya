<?php

namespace App\Form;

use App\Entity\Parameter;
use Doctrine\DBAL\Types\FloatType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParameterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('adminMail', EmailType::class, [
                'label' => 'Mail administrateur',
            ])
            ->add('criticalStock', IntegerType::class, [
                'label' => 'Stock critique',
            ])
            ->add('relayDeliveryPrice', NumberType::class, [
                'label' => 'Prix livraison relay',
            ])
            ->add('homeDeliveryPrice', NumberType::class, [
                'label' => 'Prix livraison home'
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Valider',
                'attr' => [
                    'class' => 'btn btn-primary',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Parameter::class,
        ]);
    }
}
