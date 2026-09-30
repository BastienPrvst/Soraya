<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Product;
use App\Form\Admin\ImageType;
use App\Repository\ParameterRepository;
use Doctrine\ORM\EntityManagerInterface;
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
            ->setFormOptions([
                'validation_groups' => ['Default'],
            ])
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
        if ($pageName === Crud::PAGE_INDEX) {
            return $this->getIndexFields();
        }

        return $this->getFormFields();
    }

    /**
     * Création : on ajoute d'abord les nouvelles catégories saisies dans le formulaire.
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            $this->addNewCategories($entityManager, $entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * Modification : même logique.
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            $this->addNewCategories($entityManager, $entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Lit le champ non mappé "newCategories" (noms séparés par des virgules),
     * réutilise les catégories déjà existantes et crée les autres.
     */
    private function addNewCategories(EntityManagerInterface $entityManager, Product $product): void
    {
        $formData = $this->getContext()->getRequest()->request->all('Product');
        $raw = $formData['newCategories'] ?? '';

        if (!is_string($raw) || trim($raw) === '') {
            return;
        }

        $names = array_unique(array_filter(array_map('trim', explode(',', $raw))));
        $repository = $entityManager->getRepository(Category::class);

        foreach ($names as $name) {
            $category = $repository->findOneBy(['name' => $name]);

            if (!$category) {
                $category = new Category();
                $category->setName($name);
                $entityManager->persist($category);
            }

            $product->addCategory($category);
        }
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
                ->setColumns(6),
            IntegerField::new('stock')
                ->setLabel('Stock')
                ->setDisabled()
                ->setColumns(3),
            TextField::new('capacity')
                ->setLabel('Contenance')
                ->setColumns(3),
            TextEditorField::new('smallDescription')
                ->setLabel('Description courte')
                ->setHelp('255 caractères maximum'),
            TextEditorField::new('description')
                ->setLabel('Description'),
            TextEditorField::new('useAdvice')
                ->setLabel('Conseils d\'utilisation'),
            TextEditorField::new('targetAdvice')
                ->setLabel('Conseils ciblés'),
            TextEditorField::new('ingredients')
                ->setLabel('Ingredients'),
            TextEditorField::new('benefits')
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
                ->setColumns(6)
                ->formatValue(function ($value) {
                    return implode(', ', $value->map(fn($c) => $c->getName())->toArray());
                }),
            TextField::new('newCategories')
                ->setLabel('Nouvelles catégories')
                ->setHelp('Optionnel : saisis un ou plusieurs noms séparés par des virgules. Ceux qui existent déjà sont réutilisés.')
                ->setFormTypeOptions([
                    'mapped' => false,
                    'required' => false,
                ])
                ->setColumns(6)
                ->onlyOnForms(),

            FormField::addColumn(6),
            FormField::addFieldset(),
            CollectionField::new('images', 'Galerie')
                ->setEntryType(ImageType::class)
                ->renderExpanded()
                ->setFormTypeOption('by_reference', false)
        ];
    }
}
