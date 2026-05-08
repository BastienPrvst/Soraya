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
                'label'    => false,
                'required' => false,
                'mapped'   => false,
                'attr'     => [
                    'class' => 'product-check-input',
                    'data-item-id' => $item->getId(),
                    'data-product-name' => $item->getProduct()->getName(),
                    'data-quantity' => $item->getQuantity(),
                ],
                'constraints' => [new IsTrue(message: 'Ce produit n\'a pas été marqué comme emballé')],
            ]);
        }

        $builder->add('validate', SubmitType::class, [
            'label' => 'Valider le colis',
            'attr'  => [
                'class' => 'btn btn-secondary btn-lg px-2',
            ],
        ]);

        $builder->add('validate_continue', SubmitType::class, [
            'label' => 'Valider et continuer',
            'attr'  => [
                'class' => 'btn btn-primary btn-lg px-2',
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
