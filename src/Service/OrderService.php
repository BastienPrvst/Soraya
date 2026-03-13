<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Enum\SessionKey;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack           $requestStack,
        private ShoppingCartService $shoppingCartService,
        private Security $security,
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
        $sessionId = bin2hex(random_bytes(32));
        $order
            ->setToken($token)
            ->setSessionId($sessionId)
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
        $session->set(SessionKey::ORDER_TOKEN->value, $order->getToken());
        $session->set(SessionKey::SESSION_ID->value, $order->getSessionId());

        return $order;
    }

    /**
     * @throws RandomException
     */
    public function findLatestOrderOrCreateOne(?string $token, array $products): ?Order
    {
        $user = $this->security->getUser();
        if ($token !== null) {
            $order = $this->entityManager->getRepository(Order::class)->findOneBy(
                [
                    'token' => $token,
                    'status' => [
                        OrderStatus::CREATED,
                        OrderStatus::DELIVERY_CHOICE,
                        OrderStatus::PENDING_PAYMENT
                    ]
                ],
                ['creationDate' => 'DESC']
            );

            if ($order === null) {
                $order = $this->buildOrder($products);
            } else {
                $this->updateOrder($order, $products);
            }
        } else {
            $order = $this->buildOrder($products);
        }

        return $order;
    }


    public function updateOrder(Order $order, array $products): void
    {
        $order->removeAllOrderItems();
        $this->createOrderItems($products, $order);
        $this->entityManager->flush();
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
                    ->setRelatedOrder($order)
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

    public function isOrderMatchingCart(Order $order, array $shoppingCart): bool
    {
        $orderItems = $order->getOrderItems();

        if (count($shoppingCart) !== count($orderItems)) {
            return false;
        }

        foreach ($orderItems as $item) {
            $id = (string) $item->getProduct()?->getId();

            if (!isset($shoppingCart[$id])) {
                return false;
            }

            if ((int)$shoppingCart[$id] !== $item->getQuantity()) {
                return false;
            }
        }

        return true;
    }

    public function verifyOrderIntegrity(Order $order): void
    {
        $this->verifyOrderOwnership($order);
        $session = $this->requestStack->getSession();
        $cart = $session->get(SessionKey::SHOPPING_CART->value, []);
        $products = $this->shoppingCartService->getCartInformations($cart);

        $isOrderMatchingCart = $this->isOrderMatchingCart($order, $products);

        if (!$isOrderMatchingCart) {
            $this->updateOrder($order, $products);
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
        }

        if (($order->getUser() === null) &&
            $session->get(SessionKey::SESSION_ID->value) !== $order->getSessionId()) {
            throw new AccessDeniedException();
        }
    }
}
