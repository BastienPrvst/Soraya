<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function removeProductStock(Product $product, int $quantity): void
    {
        $updated = $this->createQueryBuilder('p')
            ->update(Product::class, 'p')
            ->set('p.stock', 'p.stock - :qty')
            ->where('p.id = :id')
            ->andWhere('p.stock >= :qty')
            ->setParameter('qty', $quantity)
            ->setParameter('id', $product->getId())
            ->getQuery()
            ->execute();

        if ($updated === 0) {
            throw new \LogicException('Stock insuffisant au moment du paiement.');
        }
    }
}
