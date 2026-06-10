<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\DeliveryMode;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use SoapFault;

readonly class DeliveryService
{
    public function __construct(
        private AddressRepository      $addressRepository,
        private MondialRelayService    $mondialRelayService,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function switchRelayToDeliver(Order $order, ?User $user = null): void
    {
        $favAddress = null;

        if ($user) {
            $favAddress = $this->addressRepository->findOneBy([
                'user' => $user,
                'isActive' => true
            ]);
        }

        $order->setDeliveryMode(DeliveryMode::HOME);

        if ($favAddress) {
            $order
                ->setDeliveryAddress($favAddress)
                ->setRelayId(null);
        }
    }

    public function switchDeliverToRelay(Order $order): void
    {
        $order->setDeliveryMode(DeliveryMode::RELAY);
    }

    /**
     * @param Order $order
     * @param string $relayId
     * @return void
     * @throws SoapFault
     */
    public function createRelayAddress(Order $order, string $relayId): void
    {
        $address = $this->mondialRelayService->getRelayAddress($relayId);
        if (empty($address)) {
            throw new \RuntimeException('Invalid relay');
        }

        $order->setRelayId($relayId);

        $orderAddress = new Address();
        $orderAddress
            ->setCity($address['City'])
            ->setStreet1($address['Street'])
            ->setCountry($address['Country'])
            ->setZipcode($address['ZipCode']);

        $this->entityManager->persist($orderAddress);

        $order->setRelayAddress($orderAddress);
        $this->entityManager->flush();
    }
}
