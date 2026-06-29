<?php

namespace Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\StockService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class StockServiceTest extends TestCase
{

    /**
     * @throws Exception
     */
    public function testAdd(): void
    {
        $product = new Product();
        $product->setStock(10);
        $productRepository = $this->createStub(ProductRepository::class);
        $stockService = new StockService($productRepository);
        $stockService->add($product, 10);
        $this->assertEquals(20, $product->getStock());
    }

    /**
     * @throws Exception
     */
    public function testRemove(): void
    {
        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects($this->once())
            ->method('removeProductStock')
            ->with($this->isInstanceOf(Product::class), 5);

        $stockService = new StockService($productRepository);
        $product = new Product();
        $stockService->removeStock($product, 5);
    }

    /**
     * @throws Exception
     */
    public function testRemoveByOrder(): void
    {
        $product1 = new Product();
        $product2 = new Product();

        $orderItem1 = new OrderItem();
        $orderItem1->setProduct($product1);
        $orderItem1->setQuantity(3);

        $orderItem2 = new OrderItem();
        $orderItem2->setProduct($product2);
        $orderItem2->setQuantity(5);

        $order = new Order();
        $order->addOrderItem($orderItem1);
        $order->addOrderItem($orderItem2);

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->expects($this->exactly(2))
            ->method('removeProductStock')
            ->willReturnCallback(function (Product $product, int $quantity) use ($product1, $product2) {
                static $callIndex = 0;
                $callIndex++;

                if ($callIndex === 1) {
                    $this->assertSame($product1, $product);
                    $this->assertSame(3, $quantity);
                } else {
                    $this->assertSame($product2, $product);
                    $this->assertSame(5, $quantity);
                }
            });

        $stockService = new StockService($productRepository);
        $stockService->removeByOrder($order);
    }
}
