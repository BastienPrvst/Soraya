<?php

namespace Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Service\WorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;

class WorkflowServiceTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testCanTransition(): void
    {
        $order    = $this->createStub(Order::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->expects($this->once())
            ->method('can')
            ->with($order, 'to_cancelled')
            ->willReturn(true);

        $registry = $this->createMock(Registry::class);
        $registry->expects($this->once())
            ->method('get')
            ->with($order, 'order_completing')
            ->willReturn($workflow);

        $workflowService = new WorkflowService($registry, $this->createStub(EntityManagerInterface::class));

        $this->assertTrue($workflowService->canTransition($order, 'to_cancelled'));
    }

    /**
     * @throws Exception|\Exception
     */
    public function testApplyTransition(): void
    {
        $order    = $this->createStub(Order::class);
        $workflow = $this->createMock(WorkflowInterface::class);
        $workflow->method('can')->willReturn(true);
        $workflow->expects($this->once())
            ->method('apply')
            ->with($order, 'to_cancelled');

        $registry = $this->createStub(Registry::class);
        $registry->method('get')->willReturn($workflow);

        $workflowService = new WorkflowService($registry, $this->createStub(EntityManagerInterface::class));
        $workflowService->applyTransition($order, 'to_cancelled');
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function testApplyTransitionThrowsIfCannotTransition(): void
    {
        $order    = $this->createStub(Order::class);
        $workflow = $this->createStub(WorkflowInterface::class);
        $workflow->method('can')->willReturn(false);

        $registry = $this->createStub(Registry::class);
        $registry->method('get')->willReturn($workflow);

        $workflowService = new WorkflowService($registry, $this->createStub(EntityManagerInterface::class));

        $this->expectException(AccessDeniedException::class);
        $workflowService->applyTransition($order, 'to_cancelled');
    }
}
