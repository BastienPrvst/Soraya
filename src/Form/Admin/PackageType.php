<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Order;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

class PackageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $order = $options['data'];

        foreach ($order->getOrderItems() as $index => $item) {
            $builder->add('item_' . $index, CheckboxType::class, [
                'label'    => sprintf(
                    '%s — Quantité : %d',
                    $item->getProduct()->getName(),
                    $item->getQuantity()
                ),
                'required' => false,
                'mapped'   => false,
                'attr'     => [
                    'data-item-id' => $item->getId(),
                    'class'        => 'btn-check',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new IsTrue(message: 'Ce produit n\'a pas été marqué comme emballé'),
                ],
            ]);
        }

        $builder->add('validate', SubmitType::class, [
            'label' => 'Valider le colis',
            'attr'  => [
                'class' => 'btn btn-secondary',
            ],
        ]);

        $builder->add('validate_continue', SubmitType::class, [
            'label' => 'Valider le colis et continuer',
            'attr'  => [
                'class' => 'btn btn-primary',
            ],
        ]);
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
