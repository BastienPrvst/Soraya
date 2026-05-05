<?php

namespace App\EventListener;

use App\Entity\Order;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
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

        $deliveryRoutes = [
            'checkout_delivery_home',
            'checkout_delivery_relay'
        ];

        $payRoutes = [
            'checkout_summary',
            'checkout_pay'
        ];


        $allRoutes = array_merge($deliveryRoutes, $payRoutes);

        if (!in_array($request->attributes->get('_route'), $allRoutes, true)) {
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
            OrderStatus::DELIVERY_CHOICE => in_array($request->attributes->get('_route'), $deliveryRoutes, true)
                ? $request->attributes->get('_route')
                : 'checkout_delivery_home',
            OrderStatus::PENDING_PAYMENT => in_array($request->attributes->get('_route'), $payRoutes, true)
            ? $request->attributes->get('_route')
            : 'checkout_summary',
            OrderStatus::PAID, OrderStatus::PENDING_SHIPPING => 'checkout_success',
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
