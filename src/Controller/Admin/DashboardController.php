<?php

namespace App\Controller\Admin;

use App\Repository\OrderRepository;
use App\Repository\UserRepository;
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
        private readonly UserRepository        $userRepository,
        private readonly OrderRepository       $orderRepository,
        private readonly AdminUrlGenerator     $adminUrlGenerator,
        private readonly ChartBuilderInterface $chartBuilder,
        private readonly RequestStack $requestStack,
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

        $countPrepare = $this->orderRepository->count([
            'status' => ['to_prepare'],
        ]);

        $countPendingShipping = $this->orderRepository->count([
            'status' => ['pending_shipping'],
        ]);

        //1. Chiffre d’affaires
        //2. ⁠Nombre de commandes
        //3. ⁠Panier moyen
        //4. ⁠Nombre de visiteurs du site



        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $months = [
            1 => 'Jan',
            2 => 'Fév',
            3 => 'Mars',
            4 => 'Avr',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juil',
            8 => 'Aout',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec'
        ];

        $request = $this->requestStack->getCurrentRequest();

        $currentYear = (int) date('Y');
        $selectedYear = (int) $request->query->get('year', $currentYear);

        $orderByMonth = $this->orderRepository->getOrderByMonth($selectedYear);

        $dataByMonth = [];
        foreach ($orderByMonth as $row) {
            $dataByMonth[(int)$row['month']] = (int)$row['total'];
        }

        $data = [];
        foreach ($months as $num => $label) {
            $data[] = $dataByMonth[$num] ?? 0;
        }

        $chart->setData([
            'labels' => array_values($months),
            'datasets' => [[
                'label' => 'Ventes par mois',
                'data' => $data,
            ]],
        ]);
        return $this->render('admin/dashboard.html.twig', [
            'toPrepareUrl' => $toPrepareUrl,
            'toShipUrl' => $toShipUrl,
            'countPrepare' => $countPrepare,
            'countPendingShipping' => $countPendingShipping,
            'chart' => $chart,
            'selectedYear' => $selectedYear,
            'years' => range(2025, $currentYear),
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
