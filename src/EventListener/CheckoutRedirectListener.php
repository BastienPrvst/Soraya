<?php

namespace App\EventListener;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

final readonly class CheckoutRedirectListener
{
    public function __construct(
        private RouterInterface $router,
        private OrderRepository $orderRepository,
    ) {
    }

    #[AsEventListener(event: 'kernel.request', priority: -10)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $orderRoutes = [
            'checkout_delivery',
            'checkout_summary',
            'checkout_success'
        ];

        if (!in_array($request->attributes->get('_route'), $orderRoutes, true)) {
            return;
        }
        $token = $request->attributes->get('token');

        if ($token === null) {
            return;
        }

        $order = $this->orderRepository->findOneBy(['token' => $token]);

        if (!$order) {
            return;
        }

        $route = match ($order->getStatus()) {
            OrderStatus::DELIVERY_CHOICE => 'checkout_delivery',
            OrderStatus::PENDING_PAYMENT => 'checkout_summary',
            OrderStatus::PAID => 'checkout_success',
            default => null,
        };

        if (!$route) {
            return;
        }

        if ($request->attributes->get('_route') !== $route) {
            $event->setResponse(
                new RedirectResponse(
                    $this->router->generate($route, ['token' => $order->getToken()])
                )
            );
        }
    }
}
