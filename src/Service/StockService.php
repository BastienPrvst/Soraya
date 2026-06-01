<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

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

    /**
     * @param Product $product
     * @param int $quantity
     * @return void
     * @return LogicException
     */
    public function remove(Product $product, int $quantity): void
    {
        $this->productRepository->removeStock($product, $quantity);
    }

    public function isAvailable(Product $product, int $quantity = 1): bool
    {
        return $product->getStock() >= $quantity;
    }
}
