<?php

namespace App\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Workflow\Registry;

readonly class WorkflowService
{
    public function __construct(
        private Registry               $registry,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function canTransition(Order $order, string $transition): bool
    {
        return $this->registry
            ->get($order, 'order_completing')
            ->can($order, $transition);
    }

    /**
     * @throws \Exception
     */
    public function applyTransition(Order $order, string $transition): void
    {
        $workflow = $this->registry->get($order, 'order_completing');

        if (!$workflow->can($order, $transition)) {
            throw new AccessDeniedException();
        }

        $workflow->apply($order, $transition);
        if ($order->getStatus()?->isAtLeast(OrderStatus::PAID)) {
            $order->setUpdatedAt(new \DateTimeImmutable('', new \DateTimeZone('Europe/Paris')));
        }

        $this->entityManager->flush();
    }
}
