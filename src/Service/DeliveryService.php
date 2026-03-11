<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\User;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;

class DeliveryService
{
    public function __construct(
        private readonly AddressRepository      $addressRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function prepareHomeDelivery(Order $order, ?User $user = null):void
    {

        $favAddress = $this->addressRepository->findOneBy(
            [
                'user' => $user,
                'isActive' => true
            ]
        );

        if ($favAddress) {
            $order->setDeliveryAddress($favAddress);
        } else {
            $actualAddress = $order->getDeliveryAddress();
            if ($actualAddress && $order->getRelayId() !== null) {
                $order->setDeliveryAddress(null);
                $order->setRelayId(null);
            }
        }
        $this->entityManager->flush();
    }
}
