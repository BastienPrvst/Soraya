<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Locale;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{

//    public function configureAssets(): Assets
//    {
//        return Assets::new()
//            ->addCssFile('styles/admin.css');
//    }

    public function index(): Response
    {
        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
         return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('ProjetSoraya')
            ->renderContentMaximized()
            ->setDefaultColorScheme('light')
            ->setLocales([
                Locale::new('fr', 'Français'),
            ])
            ;
    }

    public function configureMenuItems(): iterable
    {
        return [
            MenuItem::linkToDashboard('Dashboard', 'fa fa-home'),

            MenuItem::linkTo(OrderCrudController::class, 'Commandes', 'fa fa-list'),

            MenuItem::linkTo(ProductCrudController::class, 'Produits', 'fa-solid fa-bag-shopping'),

            MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fa fa-users'),

        ];
    }
}
