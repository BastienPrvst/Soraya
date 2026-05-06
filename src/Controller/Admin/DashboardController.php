<?php

namespace App\Controller\Admin;

use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Locale;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OrderRepository $orderRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    )
    {
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('styles/admin.css');
    }

    public function index(): Response
    {
        $toPrepareUrl = $this->adminUrlGenerator
            ->setController(OrderCrudController::class)
            ->setAction('index')
            ->set('filters[status][value][]', 'to_prepare')
            ->set('filters[status][comparison]', '=')
            ->generateUrl();

        $toShipUrl = $this->adminUrlGenerator
            ->setController(OrderCrudController::class)
            ->setAction('index')
            ->set('filters[status][value][]', 'pending_shipping')
            ->set('filters[status][comparison]', '=')
            ->generateUrl();

        $countPrepare = $this->orderRepository->count([
            'status' => ['to_prepare'],
        ]);

        $countPendingShipping = $this->orderRepository->count([
            'status' => ['pending_shipping'],
        ]);

        return $this->render('admin/dashboard.html.twig', [
            'toPrepareUrl' => $toPrepareUrl,
            'toShipUrl' => $toShipUrl,
            'countPrepare' => $countPrepare,
            'countPendingShipping' => $countPendingShipping,
        ]);
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
