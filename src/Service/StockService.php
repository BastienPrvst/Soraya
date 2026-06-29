<?php

namespace App\Service;

use App\Entity\Order;
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
    public function removeStock(Product $product, int $quantity): void
    {
        $this->productRepository->removeProductStock($product, $quantity);
    }

    /**
     * @param Order $order
     * @return void
     * @info Le check des stocks sur le tunnel de paiement garantie un stock valide sauf achat au meme moment.
     */
    public function removeByOrder(Order $order): void
    {
        foreach ($order->getOrderItems() as $orderItem) {
            $quantity = $orderItem->getQuantity();
            $this->productRepository->removeProductStock($orderItem->getProduct(), $quantity);
        }
    }

    public function isAvailable(Product $product, int $quantity = 1): bool
    {
        return $product->getStock() >= $quantity;
    }
}
