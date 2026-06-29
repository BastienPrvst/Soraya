<?php

namespace App\Controller\Admin;

use App\Service\Admin\DashboardService;
use Doctrine\DBAL\Exception;
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

        $toRefundUrl = $this->adminUrlGenerator
            ->setController(OrderCrudController::class)
            ->setAction('index')
            ->set('filters[status][value][]', 'refund_pending')
            ->set('filters[status][comparison]', '=')
            ->generateUrl();

        $toCancelUrl = $this->adminUrlGenerator
            ->setController(OrderCrudController::class)
            ->setAction('index')
            ->set('filters[status][value][]', 'cancelled')
            ->set('filters[status][comparison]', '=')
            ->generateUrl();

        $data = $this->dashboardService->getDashboard();

        $months = [
            1 => 'Jan',
            2 => 'Fev',
            3 => 'Mar',
            4 => 'Avr',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juil',
            8 => 'Aout',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec',
        ];

        return $this->render('admin/dashboard.html.twig', [
            'toPrepareUrl' => $toPrepareUrl,
            'toShipUrl' => $toShipUrl,
            'toRefundUrl' => $toRefundUrl,
            'toCancelUrl' => $toCancelUrl,
            'countPrepare' => $data['countPrepare'],
            'countPendingShipping' => $data['countPendingShipping'],
            'countRefunded' => $data['countRefunded'],
            'countCancelled' => $data['countCancelled'],
            'chart' => $data['chart'],
            'chart2' => $data['chart2'],
            'chart3' => $data['chart3'],
            'selectedYear' => $data['selectedYear'],
            'selectedMonth' => $data['selectedMonth'],
            'currentYear' => $data['currentYear'],
            'currentMonth' => $data['currentMonth'],
            'months' => $months,
            'years' => range(2025, $data['currentYear']),
            'parameters' => $data['parameters']
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
