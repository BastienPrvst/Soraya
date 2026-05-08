<?php

namespace App\Controller\Admin;

use App\Controller\Admin\Fields\OrderAddressType;
use App\Controller\Admin\Filters\OrderStatusFilter;
use App\Entity\Order;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Form\Admin\OrderItemType;
use App\Form\Admin\OrderUserType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use phpDocumentor\Reflection\Types\This;

class OrderCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

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

        $editDeleteGroup = ActionGroup::new('actions', '')
            ->setIcon('fa fa-ellipsis-v')
            ->addAction(
                Action::new(Action::EDIT, 'Modifier')
                    ->setIcon('fa fa-pencil')
                    ->linkToCrudAction(Action::EDIT)  // ✅
            )
            ->addAction(
                Action::new(Action::DELETE, 'Supprimer')
                    ->setIcon('fa fa-trash')
                    ->addCssClass('text-danger')
                    ->linkToCrudAction(Action::DELETE)  // ✅
            );

        $statusActions = [
            'to_prepare' => [
                'label'     => 'A préparer',
                'status'    => OrderStatus::TO_PREPARE,
                'css_class' => 'btn-success'
            ],
            'pending_shipping' => [
                'label'     => 'A expédier',
                'status'    => OrderStatus::PENDING_SHIPPING,
                'css_class' => 'btn-primary'
            ],
            'pending_refund' => [
                'label'     => 'En attente de remboursement',
                'status'    => OrderStatus::PENDING_REFUND,
                'css_class' => 'btn-warning',
            ],
            'canceled' => [
                'label'     => 'Annulées',
                'status'    => OrderStatus::CANCELED,
                'css_class' => 'btn-danger'
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
                    ->addCssClass($config['css_class'])
                    ->createAsGlobalAction()
            );
        }

        $mailActions = ActionGroup::new('mails', 'Mails')
            ->setIcon('fa fa-envelope')
            ->addAction(Action::new('confirmation', 'Mail de confirmation')
                ->displayIf(static function (Order $order) {
                    return $order->getStatus()?->isAtLeast(OrderStatus::PAID);
                })
                ->linkToRoute('admin_confirmation_mail', function (Order $order) {
                    return [
                    'token' => $order->getToken(),
                    ];
                }));

        $deliveryActions = ActionGroup::new('delivery', 'Livraison')
            ->setIcon('fa fa-truck')
            ->addAction(
                Action::new(
                    'livraison',
                    'Imprimer l`\'etiquette'
                )
                    ->linkToRoute('admin_delivery')
                    ->renderAsButton()
                    ->displayIf(static function (Order $order) {
                        return $order->getStatus()?->isAtLeast(OrderStatus::PENDING_SHIPPING);
                    })
            );

        $packageAction = Action::new('package', 'Préparer')
            ->setIcon('fa-solid fa-box')
            ->linkToRoute(
                'admin_package_order',
                fn(Order $order) => ['token' => $order->getToken()]
            )
            ->renderAsLink()
            ->displayIf(static function (Order $order) {
                return $order->getStatus() === OrderStatus::TO_PREPARE;
            });

        return $actions
            ->add(Crud::PAGE_EDIT, $mailActions)
            ->add(Crud::PAGE_INDEX, $deliveryActions)
            ->add(Crud::PAGE_EDIT, $deliveryActions)
            ->add(Crud::PAGE_INDEX, $packageAction)
            ->add(Crud::PAGE_EDIT, $packageAction)
            ->add(Crud::PAGE_INDEX, $editDeleteGroup)
            ->reorder(Crud::PAGE_INDEX, [
                'package',
                'delivery',
                'actions'
            ])
            ->reorder(Crud::PAGE_EDIT, [
                Action::SAVE_AND_RETURN,
                Action::SAVE_AND_CONTINUE,
                'package',
                'delivery',
                'mails'
            ])
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_INDEX, Action::EDIT);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(
            OrderStatusFilter::new('status')
        );
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === CRUD::PAGE_INDEX) {
            return $this->getIndexFields();
        }

        return $this->getFormFields();
    }

    private function getIndexFields(): iterable
    {
        return [
            FormField::addColumn(6),
            IdField::new('betterId')
                ->setLabel('ID')
                ->setCssClass('fw-bold')
            ,
            TextField::new('user')
                ->setLabel('Client')
                ->renderAsHtml()
                ->formatValue(function ($value, Order $order) {
                    if ($order->getUser() !== null) {
                        $url = $this->adminUrlGenerator
                            ->setController(UserCrudController::class)
                            ->setAction(Action::DETAIL)
                            ->setEntityId($order->getUser()->getId())
                            ->generateUrl();

                        return sprintf(
                            '<a href="%s">%s %s</a>',
                            $url,
                            $order->getUser()->getFirstname(),
                            $order->getUser()->getLastname()
                        );
                    }

                    return sprintf('%s %s', $order->getFirstname(), $order->getLastname());
                }),
            MoneyField::new('total')
                ->setCurrency('EUR')
                ->setStoredAsCents(false),
            DateField::new('creationDate')
                ->setLabel('Date')
                ->setFormat('dd/MM/YYYY')
                ->setTimezone('Europe/Paris'),
            ChoiceField::new('status')
                ->setLabel('Statut')
                ->setChoices(
                    array_combine(
                        array_map(fn(OrderStatus $s) => $s->label(), OrderStatus::cases()),
                        array_map(fn(OrderStatus $s) => $s->value, OrderStatus::cases())
                    )
                )
                ->renderAsBadges([
                    OrderStatus::TO_PREPARE->value   => 'success',
                    OrderStatus::PENDING_SHIPPING->value      => 'primary',
                    OrderStatus::SHIPPED->value => 'success',
                    OrderStatus::REFUND->value    => 'warning',
                    OrderStatus::CANCELED->value  => 'danger',
                ])
                ->setSortable(true),
            TextField::new('deliveryModeLabel')
                ->setLabel('Livraison')
                ->setSortable(true),
            NumberField::new('totalQuantity')
                ->setLabel('Nbr de produits')
                ->setTextAlign('center'),
        ];
    }

    private function getFormFields(): iterable
    {
        return [
            FormField::addColumn(6),
            FormField::addFieldset(),
            MoneyField::new('total')
                ->setCurrency('EUR')
                ->setStoredAsCents(false)
                ->setColumns(3),
            DateField::new('creationDate')
                ->setLabel('Date')
                ->setFormat('dd/MM/YYYY')
                ->setTimezone('Europe/Paris')
                ->setColumns(4),
            ChoiceField::new('status')
                ->setLabel('Statut')
                ->setChoices(
                    array_combine(
                        array_map(fn(OrderStatus $s) => $s->label(), OrderStatus::cases()),
                        OrderStatus::cases()
                    )
                )
                ->setFormTypeOption(
                    'choice_value',
                    fn($value) => $value instanceof OrderStatus ? $value->value : $value
                )
                ->setColumns(4),
            ChoiceField::new('deliveryMode')
                ->setLabel('Mode')
                ->setChoices(
                    array_combine(
                        array_map(fn(DeliveryMode $s) => $s->label(), DeliveryMode::cases()),
                        DeliveryMode::cases()
                    )
                )
                ->setFormTypeOption(
                    'choice_value',
                    fn($value) => $value instanceof DeliveryMode ? $value->value : $value
                ),
            CollectionField::new('orderItems')
                ->setLabel('Articles')
                ->setEntryType(OrderItemType::class)
                ->allowAdd(false)
                ->allowDelete(false),
            FormField::addColumn(6),
            FormField::addFieldset('Client')
                ->setIcon('fa fa-user'),
            TextField::new('firstname')
                ->setLabel('Prénom')
                ->setColumns(6),
            TextField::new('lastname')
                ->setColumns(6)
                ->setLabel('Nom'),
            TelephoneField::new('phoneNumber')
                ->setLabel('Tel.')
                ->setColumns(6),
            EmailField::new('email')
                ->setLabel('Email')
                ->setColumns(6),
            FormField::addFieldset('Adresse')
                ->setIcon('fa fa-home'),
            Field::new('deliveryAddress')
                ->setFormType(OrderAddressType::class)
                ->setLabel('Livraison')
        ];
    }
}
