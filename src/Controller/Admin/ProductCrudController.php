<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\Admin\ImageType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProductCrudController extends AbstractCrudController
{
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
            ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name')
                ->setLabel('Nom'),
            TextEditorField::new('description')
                ->setLabel('Description')
                ->onlyOnForms()
            ,
            MoneyField::new('price')
                ->setCurrency('EUR')
                ->setLabel('Prix')
                ->setStoredAsCents(false)
                ->setTextAlign('left'),
            NumberField::new('weight')
                ->setLabel('Poids'),
            AssociationField::new('category')
                ->setLabel('Catégories')
                ->formatValue(function ($value) {
                    return implode(', ', $value->map(fn($c) => $c->getName())->toArray());
                })
            ,
            CollectionField::new('images', 'Galerie')
                ->setEntryType(ImageType::class)
                ->allowAdd()
                ->allowDelete()
                ->renderExpanded()
                ->setFormTypeOption('by_reference', false)
                ->onlyOnForms()
        ];
    }
}
