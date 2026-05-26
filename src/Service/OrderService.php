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
use Doctrine\ORM\EntityManagerInterface;
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
        private WorkflowService        $workflowService
    ) {
    }

    /**
     * @throws RandomException
     */
    public function buildOrder(array $products): Order
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
            ->setCreationDate(new \DateTime())
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

        $this->createOrderItems($products, $order);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $session = $this->requestStack->getSession();
        $session->set(SessionElements::ORDER_TOKEN->value, $order->getToken());
        $session->set(SessionElements::SESSION_KEY->value, $order->getSessionKey());

        return $order;
    }

    /**
     * @throws RandomException
     */
    public function findLatestOrderOrCreateOne(?string $token, array $products): ?Order
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
            return $this->buildOrder($products);
        }

        $this->updateOrder($order);
        return $order;
    }

    private function createOrderItems(array $products, Order $order): void
    {
        $cartTotal = 0;
        foreach ($products as $product) {
            $orderItem = new OrderItem();
            if (!empty($product)) {
                $orderItem
                    ->setProduct($product['product'])
                    ->setQuantity($product['quantity'])
                    ->setOrder($order)
                    ->setUnitPrice($product['price'])
                    ->setTotal($product['price'] * $product['quantity'])
                ;

                $order->addOrderItem($orderItem);
                $this->entityManager->persist($orderItem);

                $cartTotal += $orderItem->getTotal();
            }
        }

        $order->setTotal($cartTotal);
    }

    public function updateOrder(Order $order): void
    {
        $cartItems = $this->requestStack
            ->getSession()
            ->get(SessionElements::SHOPPING_CART->value, []);

        if (empty($cartItems)) {
            if ($this->workflowService->canTransition($order, OrderStatus::CANCELLED->value)) {
                $this->workflowService->applyTransition($order, OrderStatus::CANCELLED->value);
            }
            $this->entityManager->flush();
            return;
        }

        $productRepo = $this->entityManager->getRepository(Product::class);
        foreach ($order->getOrderItems() as $orderItem) {
            $this->entityManager->remove($orderItem);
        }

        $order->getOrderItems()->clear();
        $total = 0;

        foreach ($cartItems as $productId => $quantity) {
            if ($quantity <= 0) {
                continue;
            }

            $product = $productRepo->find($productId);

            if (!$product) {
                continue;
            }

            $orderItem = new OrderItem();
            $orderItem
                ->setOrder($order)
                ->setProduct($product)
                ->setQuantity($quantity)
                ->setUnitPrice($product->getPrice())
                ->setTotal($product->getPrice() * $quantity);

            $this->entityManager->persist($orderItem);
            $total += $orderItem->getTotal();
        }
        $order->setTotal($total);
        $this->entityManager->flush();
    }

    public function verifyOrderIntegrity(Order $order): void
    {
        $this->verifyOrderOwnership($order);
        $session = $this->requestStack->getSession();
        $cart = $session->get(SessionElements::SHOPPING_CART->value, []);

        $isOrderMatchingCart = $this->isOrderMatchingCart($order, $cart);
        if (!$isOrderMatchingCart) {
            $this->updateOrder($order);
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
            $id = (string) $item->getProduct()?->getId();

            if (!isset($cart[$id])) {
                return false;
            }

            if ((int)$cart[$id] !== $item->getQuantity()) {
                return false;
            }
        }

        return true;
    }

}
