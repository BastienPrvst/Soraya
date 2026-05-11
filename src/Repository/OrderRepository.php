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
    public function getOrderByMonth(int $year): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
        SELECT
            MONTH(creation_date) AS month,
            COUNT(id) AS total
        FROM `order`
        WHERE YEAR(creation_date) = :year
        GROUP BY month
        ORDER BY month ASC
        ';

        return $conn
            ->executeQuery($sql, ['year' => $year])
            ->fetchAllAssociative();
    }
}
