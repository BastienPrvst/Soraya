<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Enum\SessionElements;
use App\Repository\ProductRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack           $requestStack,
        private Security               $security,
        private WorkflowService        $workflowService,
        private StockService           $stockService,
        private ProductRepository      $productRepository,
    ) {
    }

    /**
     * @throws RandomException
     */
    public function buildOrder(array $cartProducts): Order
    {
        /* @var User $user */
        $user = $this->security->getUser();

        $order = new Order();
        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));
        $order
            ->setToken($token)
            ->setSessionKey($sessionKey)
            ->setUser($user)
            ->setFirstname($user?->getFirstname())
            ->setLastname($user?->getLastname())
            ->setPhoneNumber(null)
            ->setCreationDate(new DateTime())
            ->setDelivery(true)
            ->setEmail($user?->getEmail())
            ->setDeliveryMode(DeliveryMode::HOME)
        ;

        if ($user) {
            $address = $this->entityManager->getRepository(Address::class)->findOneBy([
                'user' => $user,
                'isActive' => true
            ]);

            if ($address) {
                $order->setDeliveryAddress($address);
            }
        }

        $isCartModified = $this->createOrderItems($cartProducts, $order);

        if ($isCartModified) {
            $session = $this->requestStack->getSession();
            $session->set(SessionElements::SHOPPING_CART->value, $cartProducts);
            $session->getFlashBag()->add(
                'warning',
                'Certains articles n\'étaient plus disponibles
                 dans les quantités demandées. Le panier a été mit à jour.'
            );
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $session = $this->requestStack->getSession();
        $session->set(SessionElements::ORDER_TOKEN->value, $order->getToken());
        $session->set(SessionElements::SESSION_KEY->value, $order->getSessionKey());

        return $order;
    }

    /**
     * @throws RandomException
     * @throws Exception
     */
    public function findLatestOrderOrCreateOne(?string $token, array $cartProducts): ?Order
    {
        $user = $this->security->getUser();
        $orderRepository = $this->entityManager->getRepository(Order::class);
        $order = null;
        if ($token !== null) {
            if ($user) {
                $order = $orderRepository->findOneBy(
                    [
                        'token' => $token,
                        'status' => [
                            OrderStatus::CREATED,
                            OrderStatus::DELIVERY_CHOICE,
                            OrderStatus::PENDING_PAYMENT
                        ],
                        'user' => $user,
                    ],
                    ['creationDate' => 'DESC']
                );
            } else {
                $session = $this->requestStack->getSession();

                $order =
                    $orderRepository->findValidAnonymousOrder(
                        $token,
                        $session->get(SessionElements::SESSION_KEY->value)
                    );
            }
        }

        if (!$order) {
            return $this->buildOrder($cartProducts);
        }

        $this->updateOrder($order, $cartProducts);
        return $order;
    }

    /**
     * @throws Exception
     * Info :
     */
    public function updateOrder(Order $order, array $cartProducts): bool
    {
        $session = $this->requestStack->getSession();

        //Si panier vide, on passe la commande en annulée
        if (empty($cartProducts)) {
            $this->cancelOrderItems($order);
            $this->entityManager->flush();
            return false;
        }

        //On reconstruit les ordersItems en fonction du panier

        foreach ($order->getOrderItems() as $orderItem) {
            $this->entityManager->remove($orderItem);
        }

        $order->getOrderItems()->clear();

        $isCartModified = $this->createOrderItems($cartProducts, $order);

        //SI modification, on met à jour la session
        if ($isCartModified) {
            $session->set(SessionElements::SHOPPING_CART->value, $cartProducts);
        }

        $this->entityManager->flush();

    }

    /**
     * @throws Exception
     */
    public function verifyOrderIntegrity(Order $order): void
    {
        $this->verifyOrderOwnership($order);
        $session = $this->requestStack->getSession();
        $cartProducts = $session->get(SessionElements::SHOPPING_CART->value, []);

        $isOrderMatchingCart = $this->isOrderMatchingCart($order, $cartProducts);
        if (!$isOrderMatchingCart) {
            $this->updateOrder($order, $cartProducts);
        }
    }

    public function verifyOrderOwnership(Order $order): void
    {
        $user = $this->security->getUser();
        $session = $this->requestStack->getSession();

        if ($order->getUser() !== null) {
            if (!$user || $order->getUser() !== $user) {
                throw new AccessDeniedException();
            }
            return;
        }

        $sessionKey = $session->get(SessionElements::SESSION_KEY->value);
        $token = $session->get(SessionElements::ORDER_TOKEN->value);

        if (!$token || !hash_equals($order->getToken(), $token)) {
            throw new AccessDeniedException();
        }

        if (!$sessionKey || !hash_equals($order->getSessionKey(), $sessionKey)) {
            throw new AccessDeniedException();
        }
    }

    public function isOrderMatchingCart(Order $order, array $cart): bool
    {
        $orderItems = $order->getOrderItems();

        if (count($cart) !== count($orderItems)) {
            return false;
        }

        foreach ($orderItems as $item) {
            $id = $item->getProduct()?->getId();

            if (!isset($cart[$id])) {
                return false;
            }

            if ((int)$cart[$id] !== $item->getQuantity()) {
                return false;
            }
        }

        return true;
    }

    private function createOrderItems(array &$cartProducts, Order $order): bool
    {
        $hasChanged = false;
        $cartTotal = 0;
        $productIds = array_keys($cartProducts);
        $products = $this->productRepository->findBy(['id' => $productIds]);

        $indexedProducts = [];
        foreach ($products as $product) {
            $indexedProducts[$product->getId()] = $product;
        }

        foreach ($cartProducts as $productId => $quantity) {
            if ($quantity <= 0) {
                unset($cartProducts[$productId]);
                $hasChanged = true;
                continue;
            }

            $product = $indexedProducts[$productId] ?? null;

            if (!$product) {
                unset($cartProducts[$productId]);
                $hasChanged = true;
                continue;
            }

            $available = $this->stockService->isAvailable($product, $quantity);

            if (!$available) {
                $stock = $product->getStock();
                if ($stock > 0) {
                    $quantity = $stock;
                    $cartProducts[$productId] = $quantity;
                } else {
                    unset($cartProducts[$productId]);
                }

                $hasChanged = true;
                if ($stock <= 0) {
                    continue;
                }
            }

            $orderItem = new OrderItem();

            $orderItem
                ->setProduct($product)
                ->setQuantity($quantity)
                ->setOrder($order)
                ->setUnitPrice($product->getPrice())
                ->setTotal($product->getPrice() * $quantity)
            ;

            $order->addOrderItem($orderItem);
            $this->entityManager->persist($orderItem);

            $cartTotal += $orderItem->getTotal();
        }

        $order->setTotal($cartTotal);

        return $hasChanged;
    }

    /**
     * @throws Exception
     */
    private function cancelOrderItems(Order $order): void
    {
        foreach ($order->getOrderItems() as $orderItem) {
            $this->entityManager->remove($orderItem);
        }
        $order->getOrderItems()->clear();
        $order->setTotal(0);

        if ($this->workflowService->canTransition($order, OrderStatus::CANCELLED->value)) {
            $this->workflowService->applyTransition($order, OrderStatus::CANCELLED->value);
        }
    }
}
