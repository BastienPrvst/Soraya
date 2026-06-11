<?php

namespace Service;

use App\DTO\OrderIntegrityResult;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\OrderService;
use App\Service\StockService;
use App\Service\WorkflowService;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Doctrine\Common\Collections\Collection;
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
     * @throws Exception|RandomException
     */
    public function testFindLatestOrderOrCreateOneWithUser(): void
    {
        $products = $this->createProductsStubs();

        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));
        $order = $this->createOrderStub($token, $sessionKey);

        $orderRepository = $this->createStub(OrderRepository::class);
        $orderRepository->method('findOneBy')->willReturn($order);
        $orderRepository->method('findValidAnonymousOrder')->willReturn($order);

        $user = $this->createUserStub();

        $orderService = $this->constructOrderService($products, null, $orderRepository, $user);
        //Normal
        $cartProducts1 = [
            $products[0]->getId() => 10,
            $products[1]->getId() => 2,
        ];

        //A update
        $cartProducts2 = [
            $products[0]->getId() => 3,
            $products[1]->getId() => 4,
            $products[2]->getId() => 5,
            $products[3]->getId() => 6,
        ];

        //A cancel
        $cartProducts3 = [];


        //test 1 normal

        $test1 = $orderService->findLatestOrderOrCreateOne($token, $sessionKey, $cartProducts1);

        $this->assertInstanceOf(Order::class, $test1->order);
        $this->assertEquals($token, $test1->order->getToken());
        $this->assertFalse($test1->updated);
        $this->assertFalse($test1->canceled);

        //Test 2 update

        $test2 = $orderService->findLatestOrderOrCreateOne($token, $sessionKey, $cartProducts2);

        $this->assertInstanceOf(Order::class, $test1->order);
        $this->assertArrayNotHasKey($products[3]->getId(), $test2->cartProducts);
        $this->assertEquals($token, $test2->order->getToken());
        $this->assertTrue($test2->updated);
        $this->assertFalse($test2->canceled);

        $test3 = $orderService->findLatestOrderOrCreateOne($token, $sessionKey, $cartProducts3);

        $this->assertTrue($test3->canceled);
    }

    /**
     * @throws RandomException
     * @throws Exception
     */
    public function testFindLatestOrderOrCreateOneWithoutUser(): void
    {
        $products = $this->createProductsStubs();

        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));
        $order = $this->createOrderStub($token, $sessionKey);

        $orderRepository = $this->createStub(OrderRepository::class);
        $orderRepository->method('findOneBy')->willReturn($order);
        $orderRepository->method('findValidAnonymousOrder')->willReturn($order);

        $orderService = $this->constructOrderService($products, null, $orderRepository, null);
        //Normal
        $cartProducts1 = [
            $products[0]->getId() => 10,
            $products[1]->getId() => 2,
        ];

        //A update
        $cartProducts2 = [
            $products[0]->getId() => 3,
            $products[1]->getId() => 4,
            $products[2]->getId() => 5,
            $products[3]->getId() => 6,
        ];

        //A cancel
        $cartProducts3 = [];


        //test 1 normal
        $test1 = $orderService->findLatestOrderOrCreateOne($token, $sessionKey, $cartProducts1);

        $this->assertInstanceOf(Order::class, $test1->order);
        $this->assertEquals($token, $test1->order->getToken());
        $this->assertFalse($test1->updated);
        $this->assertFalse($test1->canceled);

        //Test 2 update

        $test2 = $orderService->findLatestOrderOrCreateOne($token, $sessionKey, $cartProducts2);

        $this->assertInstanceOf(Order::class, $test1->order);
        $this->assertArrayNotHasKey($products[3]->getId(), $test2->cartProducts);
        $this->assertEquals($token, $test2->order->getToken());
        $this->assertTrue($test2->updated);
        $this->assertFalse($test2->canceled);

        //Canceled

        $test3 = $orderService->findLatestOrderOrCreateOne($token, $sessionKey, $cartProducts3);

        $this->assertTrue($test3->canceled);

        //Create Order

        $orderService = $this->constructOrderService($products);
        $test4 = $orderService->findLatestOrderOrCreateOne($token, $sessionKey, $cartProducts1);
        $this->assertEquals($token, $order->getToken());
        $this->assertFalse($test4->updated);
        $this->assertFalse($test4->canceled);
    }

    /**
     * @throws Exception
     * @throws RandomException
     */
    public function testBuildOrder(): void
    {
        $products = $this->createProductsStubs();

        $cartProducts = [
            $products[0]->getId() => 12,
            $products[1]->getId() => 3,
            $products[2]->getId() => 15,
            $products[3]->getId() => 4,
        ];

        $user = $this->createUserStub();

        $orderService = $this->constructOrderService($products, null, null, $user);

        $orderIntegrityResult = $orderService->buildOrder($cartProducts);
        $order = $orderIntegrityResult->order;
        $newCart = $orderIntegrityResult->cartProducts;

        $this->assertTrue($orderIntegrityResult->updated);
        $this->assertEquals($newCart[$products[0]->getId()], $cartProducts[$products[0]->getId()]);
        $this->assertEquals($newCart[$products[1]->getId()], $cartProducts[$products[1]->getId()]);
        $this->assertEquals(10, $newCart[$products[2]->getId()]);
        $this->assertArrayNotHasKey($products[3]->getId(), $newCart);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(OrderStatus::CREATED, $order->getStatus());
        $this->assertEquals(DeliveryMode::HOME, $order->getDeliveryMode());
        $this->assertEquals('John', $order->getFirstName());
        $this->assertEqualsWithDelta(
            new \DateTime(),
            $order->getCreationDate(),
            1
        );

        //Test panier qui se vide

        $cartProducts = [
            $products[3]->getId() => 12,
        ];

        $orderIntegrityResult = $orderService->buildOrder($cartProducts);

        $this->assertTrue($orderIntegrityResult->canceled);
    }

    /**
     * @throws RandomException
     * @throws Exception
     * @throws \Exception
     */
    public function testIntegrityOrderOk(): void
    {
        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));
        $order = $this->createOrderStub($token, $sessionKey);
        $products = $this->createProductsStubs();

        $cartProducts = [
            $products[0]->getId() => 12,
            $products[1]->getId() => 3,
            $products[2]->getId() => 5,
        ];

        $orderService = $this->constructOrderService($products);

        $orderIntegrityResult = $orderService->verifyOrderIntegrity(
            $order,
            $cartProducts,
            $token,
            $sessionKey
        );

        $this->assertFalse($orderIntegrityResult->updated);
        $this->assertFalse($orderIntegrityResult->canceled);
        $this->assertEquals($cartProducts, $orderIntegrityResult->cartProducts);
    }

    /**
     * @throws RandomException
     * @throws Exception
     * @throws \Exception
     */
    public function testIntegrityOrderUpdated(): void
    {
        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));
        $order = $this->createOrderStub($token, $sessionKey);

        $products = $this->createProductsStubs();
        $orderService = $this->constructOrderService($products);

        $cartProducts = [
            $products[0]->getId() => 12,
            $products[1]->getId() => 0,
            $products[2]->getId() => 5,
            $products[3]->getId() => 6,
            99 => 10,

        ];


        $orderIntegrityResult = $orderService->verifyOrderIntegrity(
            $order,
            $cartProducts,
            $token,
            $sessionKey
        );

        $this->assertTrue($orderIntegrityResult->updated);
        $this->assertFalse($orderIntegrityResult->canceled);

        $this->assertArrayNotHasKey($products[3]->getId(), $orderIntegrityResult->cartProducts);
        $this->assertArrayNotHasKey(99, $orderIntegrityResult->cartProducts);
        $this->assertArrayNotHasKey($products[1]->getId(), $orderIntegrityResult->cartProducts);
    }

    /**
     * @throws RandomException
     * @throws Exception
     * @throws \Exception
     */
    public function testIntegrityOrderCanceled(): void
    {
        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));
        $order = $this->createOrderStub($token, $sessionKey);
        $products = $this->createProductsStubs();
        $orderService = $this->constructOrderService($products);
        $cartProducts = [];


        $orderIntegrityResult = $orderService->verifyOrderIntegrity(
            $order,
            $cartProducts,
            $token,
            $sessionKey
        );

        $this->assertTrue($orderIntegrityResult->canceled);
        $this->assertEmpty($orderIntegrityResult->cartProducts);
    }

    /**
     * @throws Exception
     * @throws RandomException
     * @throws \Exception
     */
    public function testIntegrityOrderCanceledFromStart(): void
    {
        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));
        $order = $this->createOrderStub($token, $sessionKey);
        $products = $this->createProductsStubs();
        $cartProducts = [];

        $workflowService = $this->createMock(WorkflowService::class);
        $workflowService->method('canTransition')->willReturn(true);
        $workflowService->expects($this->once())
            ->method('applyTransition')
            ->with($order, OrderStatus::CANCELLED->value);

        $orderService = $this->constructOrderService($products, $workflowService);

        $orderIntegrityResult = $orderService->verifyOrderIntegrity(
            $order,
            $cartProducts,
            $token,
            $sessionKey
        );

        $this->assertTrue($orderIntegrityResult->canceled);
        $this->assertFalse($orderIntegrityResult->updated);
        $this->assertEmpty($orderIntegrityResult->cartProducts);
    }

    /**
     * @throws Exception
     * @throws RandomException
     * @throws \Exception
     */
    public function testIntegrityOrderCanceledDuringProcess(): void
    {
        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));
        $order = $this->createOrderStub($token, $sessionKey);
        $products = $this->createProductsStubs();

        $cartProducts = [
            $products[3]->getId() => 2,
        ];

        $workflowService = $this->createMock(WorkflowService::class);
        $workflowService->method('canTransition')->willReturn(true);
        $workflowService->expects($this->once())
            ->method('applyTransition')
            ->with($order, OrderStatus::CANCELLED->value);

        $orderService = $this->constructOrderService($products, $workflowService);

        $orderIntegrityResult = $orderService->verifyOrderIntegrity(
            $order,
            $cartProducts,
            $token,
            $sessionKey
        );

        $this->assertTrue($orderIntegrityResult->canceled);
        $this->assertTrue($orderIntegrityResult->updated);
        $this->assertEmpty($orderIntegrityResult->cartProducts);
    }

    /**
     * @throws RandomException
     * @throws Exception
     */
    public function testVerifyOrderOwnerShipNoUserGoodToken(): void
    {
        $products = $this->createProductsStubs();
        $orderService = $this->constructOrderService($products);
        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));
        $order = $this->createOrderStub($token, $sessionKey);

        //No user + good tokens

        $orderService->verifyOrderOwnership(
            $order,
            $token,
            $sessionKey
        );

        $this->addToAssertionCount(1);
    }

    /**
     * @throws RandomException
     * @throws Exception
     */
    public function testVerifyOrderOwnershipNoUserBadToken(): void
    {
        $orderService = $this->constructOrderService($this->createProductsStubs());
        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));

        $order = $this->createOrderStub($token, $sessionKey);

        $this->expectException(AccessDeniedException::class);
        $orderService->verifyOrderOwnership($order, 'mauvais_token', $sessionKey);
    }

    /**
     * @throws Exception
     * @throws RandomException
     */
    public function testVerifyOrderOwnershipNoUserBadSessionKey(): void
    {
        $orderService = $this->constructOrderService($this->createProductsStubs());
        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));
        $order = $this->createOrderStub($token, $sessionKey);

        $this->expectException(AccessDeniedException::class);
        $orderService->verifyOrderOwnership($order, $token, 'mauvaise_session_key');
    }

    /**
     * @throws Exception
     */
    public function testVerifyOrderOwnershipWithMatchingUser(): void
    {
        $user = $this->createStub(User::class);
        $orderService = $this->constructOrderService($this->createProductsStubs(), null, null, $user);

        $order = $this->createStub(Order::class);
        $order->method('getUser')->willReturn($user);

        $orderService->verifyOrderOwnership($order, null, null);
        $this->addToAssertionCount(1);
    }

    /**
     * @throws Exception
     */
    public function testVerifyOrderOwnershipWithWrongUser(): void
    {
        $notOrderUser = $this->createStub(User::class);
        $orderService = $this->constructOrderService($this->createProductsStubs(), null, null, $notOrderUser);

        $orderUser = $this->createStub(User::class);
        $order = $this->createStub(Order::class);
        $order->method('getUser')->willReturn($orderUser);

        $this->expectException(AccessDeniedException::class);
        $orderService->verifyOrderOwnership($order, null, null);
    }

    /**
     * @throws Exception
     */
    public function testIsOrderMatchingCart(): void
    {
        $products = $this->createProductsStubs();

        $orderService = $this->constructOrderService($products);

        // Prépare les orderItems stubs
        $item1 = $this->createStub(OrderItem::class);
        $item1->method('getProduct')->willReturn($products[0]);
        $item1->method('getQuantity')->willReturn(10);

        $item2 = $this->createStub(OrderItem::class);
        $item2->method('getProduct')->willReturn($products[1]);
        $item2->method('getQuantity')->willReturn(5);

        $orderItems = new ArrayCollection([$item1, $item2]);

        $order = $this->createStub(Order::class);
        $order->method('getOrderItems')->willReturn($orderItems);

        // Cas 1 — cart identique à l'order
        $cart = [1 => 10, 2 => 5];
        $this->assertTrue($orderService->isOrderMatchingCart($order, $cart));

        // Cas 2 — quantité différente
        $cart = [1 => 10, 2 => 99];
        $this->assertFalse($orderService->isOrderMatchingCart($order, $cart));

        // Cas 3 — produit manquant dans le cart
        $cart = [1 => 10];
        $this->assertFalse($orderService->isOrderMatchingCart($order, $cart));

        // Cas 4 — produit en plus dans le cart
        $cart = [1 => 10, 2 => 5, 3 => 2];
        $this->assertFalse($orderService->isOrderMatchingCart($order, $cart));

        // Cas 5 — cart vide
        $this->assertFalse($orderService->isOrderMatchingCart($order, []));

        //Cas 6 - un id manque
        $cart = [1 => 10, 99 => 5];
        $this->assertFalse($orderService->isOrderMatchingCart($order, $cart));
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function testUpdateOrder(): void
    {
        $products = $this->createProductsStubs();
        $orderService = $this->constructOrderService($products);
        $order = $this->createStub(Order::class);
        $orderIntegrityResult = new OrderIntegrityResult(
            false,
            false,
            [],
            [],
            $order
        );

        //Test normal
        $cartProducts = [
            $products[1]->getId() => 2,
        ];
        $indexedProducts = [
            $products[1]->getId() => $products[3],
        ];

        $this->assertFalse($orderService->updateOrder(
            $cartProducts,
            $indexedProducts,
            $orderIntegrityResult
        )->updated);

        //Test updated

        $cartProducts = [
            2 => 2,
            99 => 2,
            1 => 0
        ];

        $product2 = $this->createStub(Product::class);
        $product2->method('getPrice')->willReturn(10.0);

        $product1 = $this->createStub(Product::class);
        $product1->method('getPrice')->willReturn(10.0);

        $indexedProducts = [
            2 => $product2,
            1 => $product1,
        ];

        $this->assertTrue($orderService->updateOrder(
            $cartProducts,
            $indexedProducts,
            $orderIntegrityResult
        )->updated);

        //Test canceled

        $cartProducts = [];
        $indexedProducts = [];
        $this->assertTrue($orderService->updateOrder(
            $cartProducts,
            $indexedProducts,
            $orderIntegrityResult
        )->canceled);

        //Test orderIntegrityCanceled

        $orderIntegrityResult->canceled = true;
        $cartProducts = [
            $products[1]->getId() => 2,
        ];
        $indexedProducts = [
            $products[1]->getId() => $products[3],
        ];

        $this->assertTrue($orderService->updateOrder(
            $cartProducts,
            $indexedProducts,
            $orderIntegrityResult
        )->canceled);
    }

    /**
     * @throws Exception
     */
    private function createProductsStubs(): array
    {
        $products = [];

        $product1 = $this->createStub(Product::class);
        $product1->method('getName')->willReturn('Product 1 test');
        $product1->method('getStock')->willReturn(1000);
        $product1->method('getId')->willReturn(1);
        $product1->method('getPrice')->willReturn(10.0);
        $products[] = $product1;

        $product2 = $this->createStub(Product::class);
        $product2->method('getName')->willReturn('Product 2 test');
        $product2->method('getStock')->willReturn(666);
        $product2->method('getId')->willReturn(2);
        $product2->method('getPrice')->willReturn(10.0);
        $products[] = $product2;

        $product3 = $this->createStub(Product::class);
        $product3->method('getName')->willReturn('Product 3 test');
        $product3->method('getStock')->willReturn(10);
        $product3->method('getId')->willReturn(3);
        $product3->method('getPrice')->willReturn(10.0);
        $products[] = $product3;

        $product4 = $this->createStub(Product::class);
        $product4->method('getName')->willReturn('Product 4 test');
        $product4->method('getStock')->willReturn(0);
        $product4->method('getId')->willReturn(4);
        $product4->method('getPrice')->willReturn(10.0);
        $products[] = $product4;

        return $products;
    }

    /**
     * @throws Exception
     */
    private function createOrderStub(
        ?string $token = null,
        ?string $sessionKey = null,
        ?User $user = null,
    ): Order {
        $order = $this->createStub(Order::class);
        $order->method('getToken')->willReturn($token);
        $order->method('getSessionKey')->willReturn($sessionKey);
        $order->method('getUser')->willReturn($user);


        return $order;
    }

    /**
     * @throws Exception
     */
    private function constructOrderService(
        array $products,
        ?WorkflowService $workflowService = null,
        ?OrderRepository $orderRepository = null,
        ?User $user = null,
    ): OrderService {

        $entityManager = $this->createStub(EntityManagerInterface::class);

        if ($orderRepository !== null) {
            $entityManager->method('getRepository')->willReturn($orderRepository);
        }

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $workflowService ??= $this->createStub(WorkflowService::class);
        $workflowService->method('canTransition')->willReturn(true);

        $productRepository = $this->createStub(ProductRepository::class);
        $productRepository->method('findBy')->willReturn($products);

        //Instanciation du stock service, car besoin d'etre testé aussi
        $stockService = new StockService($productRepository);

        return new OrderService(
            $entityManager,
            $security,
            $workflowService,
            $stockService,
            $productRepository,
        );
    }

    /**
     * @throws Exception
     */
    private function createUserStub(): User
    {
        $user = $this->createStub(User::class);
        $user->method('getFirstname')->willReturn('John');
        $user->method('getLastname')->willReturn('Doe');
        $user->method('getEmail')->willReturn('john.doe@example.com');

        return $user;
    }
}
