<?php

namespace App\Service;

use App\Entity\Order;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class OrderFinalizer
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerService $mailer,
        private StockService $stockService,
        private WorkflowService $workflowService,
        private MondialRelayService $mondialRelay,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function finalize(Order $order): void
    {
        if ($order->getStatus() !== OrderStatus::PAID) {
            return; // déjà finalisée ou pas payée
        }

        $this->logger->debug('Statut commande', [
            'status' => $order->getStatus()?->value,
        ]);

        if ($this->workflowService->canTransition($order, 'to_pending_delivery')) {
            $this->workflowService->applyTransition($order, 'to_pending_delivery');
        } else {
            return;
        }

        // 2. Étapes non critiques : chacune isolée, aucune ne bloque l'autre
        $this->safely($order, 'email', function () use ($order) {
            $this->mailer->sendOrderConfirmationEmail($order);
        });

        if ($order->getDeliveryMode() === DeliveryMode::RELAY && $order->getShipmentNumber() === null) {
            $this->safely($order, 'étiquette', function () use ($order) {
                $result = $this->mondialRelay->shippingCreation($order);
                $order->setShipmentNumber($result['shipmentNumber']);
            });
        }

        $this->em->flush();
    }

    private function safely(Order $order, string $step, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            $this->logger->error("Finalisation commande : échec « $step »", [
                'order' => $order->getId(),
                'exception' => $e,
            ]);
        }
    }
}
