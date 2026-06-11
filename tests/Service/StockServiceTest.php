<?php

namespace Service;

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
            ->method('removeStock')
            ->with($this->isInstanceOf(Product::class), 5);

        $stockService = new StockService($productRepository);
        $product = new Product();
        $stockService->remove($product, 5);
    }
}
