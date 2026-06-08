<?php

namespace Service;

use App\DTO\OrderIntegrityResult;
use App\Entity\Order;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\OrderService;
use App\Service\StockService;
use App\Service\WorkflowService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderServiceTest extends TestCase
{

    /**
     * @throws Exception
     * @throws RandomException
     */
    public function testBuildOrder(): void
    {
        $products = [];

        $product1 = $this->createMock(Product::class);
        $product1->method('getName')->willReturn('Product 1 test');
        $product1->method('getStock')->willReturn(1000);
        $product1->method('getId')->willReturn(1);
        $product1->method('getPrice')->willReturn(10.0);
        $products[] = $product1;

        $product2 = $this->createMock(Product::class);
        $product2->method('getName')->willReturn('Product 2 test');
        $product2->method('getStock')->willReturn(666);
        $product2->method('getId')->willReturn(2);
        $product2->method('getPrice')->willReturn(10.0);
        $products[] = $product2;

        $product3 = $this->createMock(Product::class);
        $product3->method('getName')->willReturn('Product 3 test');
        $product3->method('getStock')->willReturn(10);
        $product3->method('getId')->willReturn(3);
        $product3->method('getPrice')->willReturn(10.0);
        $products[] = $product3;

        $product4 = $this->createMock(Product::class);
        $product4->method('getName')->willReturn('Product 4 test');
        $product4->method('getStock')->willReturn(0);
        $product4->method('getId')->willReturn(4);
        $product4->method('getPrice')->willReturn(10.0);
        $products[] = $product4;

        $cartProducts = [
            $product1->getId() => 12,
            $product2->getId() => 3,
            $product3->getId() => 15,
            $product4->getId() => 4,
        ];



        $orderService = $this->constructOrderService($products);

        $orderIntegrityResult = $orderService->buildOrder($cartProducts);
        $order = $orderIntegrityResult->order;
        $newCart = $orderIntegrityResult->cartProducts;

        $this->assertEquals($newCart[$product1->getId()], $cartProducts[$product1->getId()]);
        $this->assertEquals($newCart[$product2->getId()], $cartProducts[$product2->getId()]);
        $this->assertNotEquals($newCart[$product3->getId()], $cartProducts[$product3->getId()]);
        $this->assertNull($newCart[$product4->getId()]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertTrue($orderIntegrityResult->updated);

    }

    /**
     * @throws Exception
     */
    private function constructOrderService(array $products): OrderService
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $security = $this->createMock(Security::class);
        $workflowService = $this->createMock(WorkflowService::class);
        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('findBy')->willReturn($products);
        $stockService = new StockService($entityManager, $productRepository);

        return new OrderService(
            $entityManager,
            $security,
            $workflowService,
            $stockService,
            $productRepository,
        );
    }

}
