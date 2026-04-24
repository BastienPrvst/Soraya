<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commande')
            ->setEntityLabelInPlural('Commandes')
            ->setSearchFields([
                'id',
                'order.user.firstname',
                'order.user.lastname',
                'status'
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->formatValue(function ($id) {
                    return '#' . sprintf('%05d', $id);
                }),
            MoneyField::new('total')
                ->setCurrency('EUR')
                ->setStoredAsCents(false),
            AssociationField::new('user')
                ->setLabel('Client')
                ->formatValue(function ($value) {
                    if (!$value) {
                        return null;
                    }
                    return $value->getFirstname() . ' ' . $value->getLastname();
                })
                ->onlyOnIndex(),
            TextField::new('statusLabel')
                ->setLabel('Statut'),
            TextField::new('deliveryModeLabel')
                ->setLabel('Livraison'),

        ];
    }
}
