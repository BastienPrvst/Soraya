<?php

namespace App\Service\Admin;

use App\Repository\OrderRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class DashboardService
{

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly RequestStack $requestStack,
        private readonly ChartBuilderInterface $chartBuilder,
    ) {
    }

    public function getDashboard(): array
    {
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

//        dd($request);

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');
        $selectedYear = (int) $request->query->get('year', $currentYear);

        $orderByMonth = $this->orderRepository->getOrdersByMonth($selectedYear);

        $dataByMonth = [];
        foreach ($orderByMonth as $row) {
            $dataByMonth[(int)$row['month']] = [
                'order_count' => (int)$row['order_count'],
                'total_price' => (float)$row['total_price'],
            ];
        }

        $data = [];
        foreach ($months as $month => $monthName) {
            if ($selectedYear === $currentYear && $month > $currentMonth) {
                break;
            }

            $orderCount = $dataByMonth[$month]['order_count'] ?? 0;
            $totalPrice = $dataByMonth[$month]['total_price'] ?? 0;

            $data[$month] = [
                'label' => $monthName,
                'order_count' => $orderCount,
                'total_price' => $totalPrice,
                'average' => $orderCount > 0 ? $totalPrice / $orderCount : 0,
            ];
        }

        //Nbr commandes par moi

        $chart = $this->chartBuilder->createChart(Chart::TYPE_BAR);

        $chart->setData([
            'labels' => array_column($data, 'label'),
            'datasets' => [
                [
                    'label' => 'Nbr commandes',
                    'data' => array_column($data, 'order_count'),
                    'backgroundColor' => '#9BD0F5',
                ],

            ],

        ]);

        //Panier moyen + CA

        $chart2 = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $chart2->setData([
            'labels' => array_column($data, 'label'),
            'datasets' => [

                [
                    'label' => 'Panier moyen',
                    'data' => array_column($data, 'average'),
                    'type' => 'bar',
                    'backgroundColor' => '#4ABEBE',
                    'borderColor' => '#4ABEBE',
                ],

                [
                    'label' => 'Chiffre d\'affaire',
                    'data' => array_column($data, 'total_price'),
                    'backgroundColor' => '#FD9E3F',
                    'borderColor' => '#FD9E3F',
                ]
            ]
        ]);

        $chart2->setOptions([
            'plugins' => [
                'datalabels' => [
                    'display' => false,
                ]
            ]
        ]);

        //Ventes par categories de produits

        $productsData = $this->orderRepository->getProductsSoldByCategories();

        $chart3 = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);

        $chart3->setData([
            'labels' => array_column($productsData, 'categories'),
            'datasets' => [
                [
                    'label' => 'Produits vendus par catégorie',
                    'data' => array_column($productsData, 'order_count'),
                ]
            ]
        ]);

        $chart3->setOptions([
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'datalabels' => [
                    'display' => true,
                    'color' => '#FFF',
                    'font' => [
                        'weight' => 'bold',
                        'size' => 18
                    ],
                ]
            ]
        ]);

        return [
            'countPrepare' => $countPrepare,
            'countPendingShipping' => $countPendingShipping,
            'chart' => $chart,
            'chart2' => $chart2,
            'chart3' => $chart3,
            'selectedYear' => $selectedYear,
            'currentYear' => $currentYear,
        ];
    }
}
