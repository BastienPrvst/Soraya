<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\Form\AbstractType;

class OrderService extends AbstractType
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
            ->setStatus(OrderStatus::CREATED)
            ->setCreationDate(new \DateTime())
            ->setDelivery(true)
            ->setEmail($user?->getEmail())
            ->setDeliveryMode(DeliveryMode::HOME)
        ;

        if ($user) {
            $address = $this->entityManager->getRepository(Address::class)->findOneBy([
                'User' => $user,
                'isActive' => true
            ]);

            if ($address) {
                $order->setDeliveryAddress($address);
            }
        }

        $this->createOrderItems($products, $order);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

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

        $order->setTotal($cartTotal);
    }
}
