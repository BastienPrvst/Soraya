<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use LogicException;

readonly class StockService
{
    public function __construct(
        private ProductRepository      $productRepository,
    ) {
    }

    public function add(Product $product, int $quantity): void
    {
        $product->setStock($product->getStock() + $quantity);
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
