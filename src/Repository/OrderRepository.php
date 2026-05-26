<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\SessionElements;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct($registry, Order::class);
    }

    public function findValidAnonymousOrder(string $token, string $sessionKey): ?Order
    {
        $session = $this->requestStack->getSession();

        return $this
            ->createQueryBuilder('o')
            ->where('o.token = :token')
            ->andWhere('o.status IN (:statuses)')
            ->andWhere('o.sessionKey = :sessionKey')
            ->andWhere('o.creationDate >= :limitDate')
            ->setParameter('token', $token)
            ->setParameter('statuses', [
                OrderStatus::CREATED,
                OrderStatus::DELIVERY_CHOICE,
                OrderStatus::PENDING_PAYMENT
            ])
            ->setParameter('sessionKey', $sessionKey)
            ->setParameter('limitDate', new \DateTime('-1 hour'))
            ->orderBy('o.creationDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getLastTenOrders(User $user): array
    {
        $statuses = [
            OrderStatus::SHIPPED,
            OrderStatus::REFUND
        ];

        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.status IN (:statuses)')
            ->orderBy('o.creationDate', 'DESC')
            ->setParameter('user', $user)
            ->setParameter('statuses', $statuses)
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    public function findNextToPrepare(Order $exclude): ?Order
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.id != :id')
            ->setParameter('status', OrderStatus::TO_PREPARE)
            ->setParameter('id', $exclude->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @throws Exception
     */
    public function getOrdersByMonth(int $year): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT
            MONTH(creation_date) AS month,
            COUNT(id) AS order_count,
            SUM(total + COALESCE(delivery_price, 0)) AS total_price
        FROM `order`
        WHERE YEAR(creation_date) = :year
        GROUP BY month
        ORDER BY month ASC
        ';

        return $conn
            ->executeQuery($sql, ['year' => $year])
            ->fetchAllAssociative();
    }

    /**
     * @throws \Exception
     */
    public function getProductsSoldByCategories(int $month): array
    {
        if (!$month
        || $month < 1
        || $month > 12) {
            $startDate = new \DateTime('first day of this month');
            $lastDay = new \DateTime('last day of this month');
        } else {
            $year      = (new \DateTime())->format('Y');
            $startDate = new \DateTime("first day of $year-$month");
            $lastDay   = new \DateTime("last day of $year-$month");
        }
        $startDate->setTime(0, 0, 0);
        $lastDay->setTime(23, 59, 59);

        $statuses = array_filter(
            OrderStatus::cases(),
            static fn($status) => $status->isAtLeast(OrderStatus::PAID)
        );

        $results = $this->createQueryBuilder('o')
            ->select(
                '
                c.name as categories',
                'SUM(oi.quantity) as product_sold',
            )
            ->leftJoin('o.orderItems', 'oi')
            ->leftJoin('oi.product', 'p')
            ->leftJoin('p.category', 'c')
            ->where('o.creationDate BETWEEN :startDate AND :endDate')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $lastDay)
            ->setParameter('statuses', $statuses)
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();

        array_walk($results, function (&$item) use ($results) {
            $item['total_product_sold'] = array_sum(array_column($results, 'product_sold'));
        });

        return $results;
    }

    public function getCanceledOfTheWeek(): int
    {
        $now = new \DateTime();
        $startOfWeek = (clone $now)->modify('monday this week')->setTime(0, 0, 0);
        $endOfWeek   = (clone $now)->modify('sunday this week')->setTime(23, 59, 59);

        return $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status IN (:status)')
            ->andWhere('o.creationDate BETWEEN :start AND :end')
            ->setParameter('status', ['cancelled'])
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
