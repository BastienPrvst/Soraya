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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpFoundation\RequestStack;

class OrderService extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @throws RandomException
     */
    public function buildOrder(array $products, ?User $user): Order
    {
        $order = new Order();
        $token = bin2hex(random_bytes(20));
        $order
            ->setToken($token)
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

        return $order;
    }

    /**
     * @throws RandomException
     */
    public function findLatestOrderOrCreateOne(?string $token, array $products, ?User $user): ?Order
    {
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
                $order = $this->buildOrder($products, $user);
            } else {
                $this->updateOrder($order, $products);
            }
        } else {
            $order = $this->buildOrder($products, $user);
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
}
