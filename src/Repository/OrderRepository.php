<?php

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\SessionKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly RequestStack $requestStack
    ) {
        parent::__construct($registry, Order::class);
    }

    public function findValidAnonymousOrder(string $token, string $sessionId): ?Order
    {
        $session = $this->requestStack->getSession();

        return $this
            ->createQueryBuilder('o')
            ->where('o.token = :token')
            ->andWhere('o.status IN (:statuses)')
            ->andWhere('o.sessionId = :sessionId')
            ->andWhere('o.creationDate >= :limitDate')
            ->setParameter('token', $token)
            ->setParameter('statuses', [
                OrderStatus::CREATED,
                OrderStatus::DELIVERY_CHOICE,
                OrderStatus::PENDING_PAYMENT
            ])
            ->setParameter('sessionId', $session->get(SessionKey::SESSION_ID->value))
            ->setParameter('limitDate', new \DateTime('-1 hour'))
            ->orderBy('o.creationDate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
