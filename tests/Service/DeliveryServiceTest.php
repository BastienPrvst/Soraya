<?php

namespace Service;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\DeliveryMode;
use App\Repository\AddressRepository;
use App\Service\DeliveryService;
use App\Service\MondialRelayService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use SoapFault;

class DeliveryServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testSwitchRelayToDeliver(): void
    {
        $deliveryService = $this->createDeliveryService();
        $order = new Order();
        $user = $this->createStub(User::class);

        $deliveryService->switchRelayToDeliver($order, $user);
        $this->assertEquals(DeliveryMode::HOME, $order->getDeliveryMode());
    }

    /**
     * @throws Exception
     */
    public function testSwitchToDeliverToRelay(): void
    {
        $deliveryService = $this->createDeliveryService();
        $order = new Order();
        $deliveryService->switchDeliverToRelay($order);
        $this->assertEquals(DeliveryMode::RELAY, $order->getDeliveryMode());
    }

    /**
     * @throws Exception
     * @throws SoapFault
     */
    public function testCreateRelayAddress(): void
    {
        $deliveryService = $this->createDeliveryService();
        $order = new Order();
        $relayId = "Id de relay test";
        $deliveryService->createRelayAddress($order, $relayId);
        $this->assertEquals('20 rue du test', $order->getRelayAddress()->getStreet1());
    }

    /**
     * @throws Exception
     */
    private function createDeliveryService(): DeliveryService
    {
        $addressRepository = $this->createStub(AddressRepository::class);
        $favAddress = $this->createStub(Address::class);
        $addressRepository->method('findOneBy')->willReturn($favAddress);

        $mondialRelayService = $this->createStub(MondialRelayService::class);
        $mondialRelayService->method('getRelayAddress')->willReturn([
        'NumPointRelais' => '00000',
        'Country' => 'France',
        'Street' => '20 rue du test',
        'ZipCode' => '75001',
        'City' => 'Paris',
        ]);

        $entityManager = $this->createStub(EntityManagerInterface::class);

        return new DeliveryService(
            $addressRepository,
            $mondialRelayService,
            $entityManager
        );
    }
}
