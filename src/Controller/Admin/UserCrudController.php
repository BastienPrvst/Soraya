<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setSearchFields(['firstname', 'lastname', 'email'])
            ->showEntityActionsInlined();
    }



    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
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
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        $ordersAction = Action::new('showOrders', 'Commandes liées')
            ->setIcon('fa fa-eye')
            ->linkToUrl(function (User $user) use ($adminUrlGenerator) {
                return $adminUrlGenerator
                    ->setController(OrderCrudController::class)
                    ->setAction(Action::INDEX)
                    ->set('filters[user][value]', $user->getId())
                    ->set('filters[user][comparison]', '=')
                    ->generateUrl();
            });

        return $actions
            ->add(Crud::PAGE_INDEX, $editDeleteGroup)
            ->add(Crud::PAGE_INDEX, $ordersAction)
            ->add(Crud::PAGE_EDIT, $ordersAction)
            ->add(Crud::PAGE_DETAIL, $ordersAction)
            ->reorder(Crud::PAGE_INDEX, ['showOrders', 'actions'])
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            ->remove(Crud::PAGE_INDEX, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('firstname')->setLabel('Prénom'),
            TextField::new('lastname')->setLabel('Nom'),
            EmailField::new('email'),
            TelephoneField::new('phoneNumber')
                ->setLabel('Tel.'),
            ChoiceField::new('roles')
                ->setChoices([
                    'Utilisateur' => 'ROLE_USER',
                    'Administrateur' => 'ROLE_ADMIN',
                ])
                ->allowMultipleChoices()
                ->renderExpanded(false),
            BooleanField::new('isActive')
                ->setLabel('Actif')
        ];
    }
}
