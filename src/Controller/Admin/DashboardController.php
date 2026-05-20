<?php

namespace App\Controller\Admin;

use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use App\Service\Admin\DashboardService;
use Doctrine\DBAL\Exception;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Locale;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly AdminUrlGenerator     $adminUrlGenerator,
    ) {
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('styles/admin.css')
            ->addAssetMapperEntry('admin');
    }

    /**
     * @throws Exception
     */
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

        $data = $this->dashboardService->getDashboard();

        return $this->render('admin/dashboard.html.twig', [
            'toPrepareUrl' => $toPrepareUrl,
            'toShipUrl' => $toShipUrl,
            'countPrepare' => $data['countPrepare'],
            'countPendingShipping' => $data['countPendingShipping'],
            'chart' => $data['chart'],
            'chart2' => $data['chart2'],
            'chart3' => $data['chart3'],
            'selectedYear' => $data['selectedYear'],
            'currentYear' => $data['currentYear'],
            'years' => range(2025, $data['currentYear']),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Lévédène')
            ->renderContentMaximized()
            ->setDefaultColorScheme('light')
            ->setLocales([
                Locale::new('fr', 'Français'),
            ]);
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
