<?php

namespace App\Service\Admin;

use App\Repository\OrderRepository;
use Doctrine\DBAL\Exception;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

readonly class DashboardService
{

    public function __construct(
        private OrderRepository       $orderRepository,
        private RequestStack          $requestStack,
        private ChartBuilderInterface $chartBuilder,
    ) {
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function getDashboard(): array
    {
        $countPrepare = $this->orderRepository->count([
            'status' => ['to_prepare'],
        ]);

        $countPendingShipping = $this->orderRepository->count([
            'status' => ['pending_shipping'],
        ]);

        $countRefunded = $this->orderRepository->count([
            'status' => ['refund_pending'],
        ]);

        $countCancelled = $this->orderRepository->getCanceledOfTheWeek();

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

        if ($request === null) {
            throw new NotFoundHttpException();
        }

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

        //Nbr commandes par mois

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

        $chart2 = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $chart2->setData([
            'labels' => array_column($data, 'label'),
            'datasets' => [
                [
                    'label' => 'Panier moyen',
                    'data' => array_column($data, 'average'),
                    'backgroundColor' => '#4ABEBE',
                    'borderColor' => '#4ABEBE',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Chiffre d\'affaire',
                    'data' => array_column($data, 'total_price'),
                    'backgroundColor' => '#FD9E3F',
                    'borderColor' => '#FD9E3F',
                    'yAxisID' => 'y2',
                ]
            ]
        ]);

        $chart2->setOptions([
            'barPercentage' => 0.9,
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Panier moyen €',
                    ],
                ],
                'y2' => [
                    'type' => 'linear',
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'CA €',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ]);

        //Ventes par categories de produits

        $selectedMonth = $request->query->get('month', $currentMonth);

        $productsData = $this->orderRepository->getProductsSoldByCategories((int)$selectedYear, (int)$selectedMonth);

        $chart3 = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);

        $productsLabels = array_map(function ($product) {
            return
                $product['categories'] .
                ' ' . $product['product_sold'] . ' (' .
                round(
                    ($product['product_sold'] / $product['total_product_sold'] * 100)
                ) . '%)';
        }, $productsData);

        $chart3->setData([
            'labels' => $productsLabels,
            'datasets' => [
                [
                    'label' => 'Vendus ce mois-ci',
                    'data' => array_column($productsData, 'product_sold'),
                ]
            ]
        ]);

        $total = !empty($productsData)
            ? array_sum(array_column($productsData, 'product_sold'))
            : 0;

        $chart3->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,

            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'left',
                    'labels' => [
                        'boxWidth' => 12,
                        'padding' => 10,
                        'font' => [
                            'size' => 12,
                            'weight' => 'bold'
                        ]
                    ]
                ],
                'title' => [
                    'display' => true,
                    'text' => [
                        'Ventes par catégories',
                        '(' . ($total) . ' produits vendus)'
                    ],
                    'align' => 'start',
                    'font' => [
                        'size' => 16,
                    ]
                ],
            ]
        ]);

        return [
            'countPrepare' => $countPrepare,
            'countPendingShipping' => $countPendingShipping,
            'countRefunded' => $countRefunded,
            'countCancelled' => $countCancelled,
            'chart' => $chart,
            'chart2' => $chart2,
            'chart3' => $chart3,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'currentMonth' => $currentMonth,
            'currentYear' => $currentYear,
        ];
    }
}
