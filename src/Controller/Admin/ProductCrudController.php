<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\Admin\ImageType;
use App\Repository\ParameterRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\PercentField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use App\Controller\Admin\Fields\CKEditorField;

class ProductCrudController extends AbstractCrudController
{
    public function __construct(
        private ParameterRepository $parameterRepository,
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Produit')
            ->setEntityLabelInPlural('Produits')
            ->setSearchFields(['name', 'category'])
            ->showEntityActionsInlined()
            ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $editDeleteGroup = ActionGroup::new('actions', '')
            ->setIcon('fa fa-ellipsis-v')
            ->addAction(
                Action::new(Action::EDIT, 'Modifier')
                    ->setIcon('fa fa-pencil')
                    ->linkToCrudAction(Action::EDIT)
            )
            ->addAction(
                Action::new(Action::DELETE, 'Supprimer')
                    ->setIcon('fa fa-trash')
                    ->addCssClass('text-danger')
                    ->linkToCrudAction(Action::DELETE)
            );

        $stockAction = Action::new('stock', 'Gérer le stock')
            ->setIcon('fa fa-box')
            ->linkToRoute(
                'admin_product_stock',
                fn(Product $product) => ['product' => $product->getId()]
            );

        return $actions
            ->add(Crud::PAGE_INDEX, $stockAction)
            ->add(Crud::PAGE_EDIT, $stockAction)
            ->add(Crud::PAGE_INDEX, $editDeleteGroup)
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_INDEX, Action::EDIT);
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
        $parameters = $this->parameterRepository->findOneBy([]);
        return [
            IntegerField::new('id')
                ->setLabel('ID')
                ->setDisabled(),
            TextField::new('name')
                ->setLabel('Nom')
                ->setCssClass('fw-bold'),
            IntegerField::new('stock')
                ->setLabel('Stock')
                ->setCssClass('fw-bold')
                ->formatValue(function ($value, Product $product) use ($parameters) {
                    $stock = $product->getStock();

                    if ($stock < $parameters->getCriticalStock()) {
                        return sprintf('<span style="color: red; font-weight: 800; background: #F9D4D4; padding: 0 5px; border-radius: 5px">%d</span><span style="font-size: 24px;">⚠️</span>', $stock);
                    }

                    return $stock;
                }),
            MoneyField::new('price')
                ->setCurrency('EUR')
                ->setLabel('Prix')
                ->setStoredAsCents(false),
            PercentField::new('percentageDiscount')
                ->setLabel('% Reduction'),
            MoneyField::new('flatDiscount')
                ->setLabel('Reduction en €')
                ->setCurrency('EUR')
                ->setStoredAsCents(false),
            AssociationField::new('category')
                ->setLabel('Catégories')
                ->formatValue(function ($value) {
                    return implode(', ', $value->map(fn($c) => $c->getName())->toArray());
                })
            ,
        ];
    }

    private function getFormFields(): iterable
    {
        return [
            FormField::addColumn(6),
            FormField::addFieldset(),
            IntegerField::new('id')
            ->setLabel('ID')
            ->setDisabled(),
            TextField::new('name')
                ->setLabel('Nom')
                ->setCssClass('fw-bold')
                ->setColumns(8),
            IntegerField::new('stock')
                ->setLabel('Stock')
                ->setDisabled()
                ->setColumns(4),
            CKEditorField::new('smallDescription')
                ->setLabel('Description courte')
                ->setHelp('255 caractères maximum'),
            CKEditorField::new('description')
                ->setLabel('Description'),
            CKEditorField::new('ingredients')
                ->setLabel('Ingredients'),
            CKEditorField::new('benefits')
                ->setLabel('Bénéfices'),
            FormField::addFieldSet('Prix'),
            MoneyField::new('price')
                ->setCurrency('EUR')
                ->setLabel('Prix')
                ->setStoredAsCents(false)
                ->setColumns(4),
            PercentField::new('percentageDiscount')
                ->setLabel('% Reduction')
                ->setColumns(4),
            MoneyField::new('flatDiscount')
                ->setLabel('Reduction en €')
                ->setCurrency('EUR')
                ->setStoredAsCents(false)
                ->setColumns(4),
            NumberField::new('weight')
                ->setLabel('Poids')
                ->formatValue(function ($value) {
                    if ($value) {
                        return $value . ' Kg';
                    }
                    return null;
                })
                ->onlyOnForms()
            ,
            AssociationField::new('category')
                ->setLabel('Catégories')
                ->formatValue(function ($value) {
                    return implode(', ', $value->map(fn($c) => $c->getName())->toArray());
                }),

            FormField::addColumn(6),
            FormField::addFieldset(),
            CollectionField::new('images', 'Galerie')
                ->setEntryType(ImageType::class)
                ->renderExpanded()
                ->setFormTypeOption('by_reference', false)
        ];
    }
}
