<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class StockService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private ProductRepository               $productRepository,
    ) {}

    public function add(Product $product, int $quantity): void
    {
        $product->setStock($product->getStock() + $quantity);
        $this->entityManager->flush();
    }

    public function remove(Product $product, int $quantity): void
    {
        $newStock = $product->getStock() - $quantity;

        if ($newStock < 0) {
            throw new \LogicException('Stock insuffisant.');
        }

        $product->setStock($newStock);
        $this->entityManager->flush();
    }

    public function isAvailable(Product $product, int $quantity = 1): bool
    {
        return $product->getStock() >= $quantity;
    }
}
