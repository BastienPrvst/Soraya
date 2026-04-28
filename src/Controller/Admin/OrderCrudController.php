<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Filters\OrderStatusFilter;
use App\Entity\Order;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Form\Admin\OrderItemType;
use App\Form\Admin\OrderUserType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

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
            ->setPageTitle(Crud::PAGE_EDIT, fn(Order $order) => 'Commande ' . sprintf('#%05d', $order->getId()))
            ->setSearchFields([
                'id',
                'user.firstname',
                'user.lastname',
            ])
            ->showEntityActionsInlined()
            ->setDefaultSort(['id' => 'DESC'])
            ->addFormTheme('admin/forms/order_items.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $urlGenerator = $this->container->get(AdminUrlGenerator::class);

        $statusActions = [
            'paid' => [
                'label' => 'Payées',
                'status' => OrderStatus::PAID,
            ],
            'pending_delivery' => [
                'label' => 'En attente de livraison',
                'status' => OrderStatus::PENDING_SHIPPING
            ],
            'pending_refund' => [
                'label' => 'En attente de remboursement',
                'status' => OrderStatus::PENDING_REFUND,
                'css_class' => 'btn btn-warning',
            ],
            'delivered' => [
                'label' => 'Livrées',
                'status' => OrderStatus::DELIVERED,
            ],
            'refund' => [
                'label' => 'Remboursées',
                'status' => OrderStatus::REFUND,
            ],
            'canceled' => [
                'label' => 'Annulées',
                'status' => OrderStatus::CANCELED,
            ],
        ];

        $statusActions = array_reverse($statusActions);

        foreach ($statusActions as $name => $config) {
            $actions = $actions->add(
                Crud::PAGE_INDEX,
                Action::new($name, $config['label'])
                    ->linkToUrl(
                        $urlGenerator
                            ->setController(self::class)
                            ->setAction(Action::INDEX)
                            ->set('filters[status][value][]', $config['status']->value)
                            ->set('filters[status][comparison]', '=')
                            ->generateUrl()
                    )
                    ->createAsGlobalAction()
            );
        }

        return $actions;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(
            OrderStatusFilter::new('status')
        );
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addColumn(7),
            IdField::new('id')
                ->formatValue(function ($id) {
                    return '#' . sprintf('%05d', $id);
                })
                ->setCssClass('fw-bold')
                ->onlyOnIndex()
            ,
            AssociationField::new('user')
                ->setLabel('Client')
                ->onlyOnIndex(),
            MoneyField::new('total')
                ->setCurrency('EUR')
                ->setStoredAsCents(false),
            DateField::new('creationDate')
                ->setLabel('Date')
                ->setFormat('d/m/Y'),
            ChoiceField::new('status')
                ->setLabel('Statut')
                ->setChoices(
                    array_combine(
                        array_map(fn(OrderStatus $s) => $s->label(), OrderStatus::cases()),
                        array_map(fn(OrderStatus $s) => $s->value, OrderStatus::cases())
                    )
                )
                ->renderAsBadges([
                    OrderStatus::CREATED->value   => 'secondary',
                    OrderStatus::PAID->value      => 'primary',
                    OrderStatus::DELIVERED->value => 'success',
                    OrderStatus::REFUND->value    => 'warning',
                    OrderStatus::CANCELED->value  => 'danger',
                ])
                ->setSortable(true)
                ->onlyOnIndex(),
            ChoiceField::new('status')
                ->setLabel('Statut')
                ->setChoices(
                    array_combine(
                        array_map(fn(OrderStatus $s) => $s->label(), OrderStatus::cases()),
                        array_map(fn(OrderStatus $s) => $s->value, OrderStatus::cases())
                    )
                )
                ->setFormTypeOption(
                    'choice_value',
                    fn($value) => $value instanceof OrderStatus ? $value->value : $value
                )
                ->onlyOnForms(),
            TextField::new('deliveryModeLabel')
                ->setLabel('Livraison')
                ->setSortable(true)
                ->onlyOnIndex(),
            ChoiceField::new('deliveryMode')
                ->setLabel('Mode')
                ->setChoices([
                    array_combine(
                        array_map(fn(DeliveryMode $s) => $s->label(), DeliveryMode::cases()),
                        array_map(fn(DeliveryMode $s) => $s->value, DeliveryMode::cases())
                    )
                ])
                ->setFormTypeOption(
                    'choice_value',
                    fn($value) => $value instanceof DeliveryMode ? $value->value : $value
                )
            ->onlyOnForms(),
            AssociationField::new('orderItems')
                ->setLabel('Nbr de produits')
                ->onlyOnIndex(),
            CollectionField::new('orderItems')
                ->setLabel('Articles')
                ->setEntryType(OrderItemType::class)
                ->allowAdd(false)
                ->allowDelete(false)
                ->onlyOnForms(),
            FormField::addColumn(5),
            FormField::addFieldset('Client')
                ->setIcon('fa fa-user')
                ->onlyOnForms(),
            TextField::new('firstname')
                ->setLabel('Prénom')
                ->onlyOnForms(),
            TextField::new('lastname')
                ->setLabel('Nom')
                ->onlyOnForms(),
            TelephoneField::new('phoneNumber')
                ->setLabel('Tel.')
                ->onlyOnForms(),
            EmailField::new('email')
                ->setLabel('Email')
                ->onlyOnForms(),
        ];
    }
}
