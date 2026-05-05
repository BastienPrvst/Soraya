<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

final class CheckoutRequestListener
{
    #[AsEventListener]
    public function onResponseEvent(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $orderRoutes = [
            'checkout_delivery_home',
            'checkout_delivery_relay',
            'checkout_summary',
            'checkout_pay'
        ];

        if (!in_array($event->getRequest()->attributes->get('_route'), $orderRoutes, true)) {
            return;
        }

        $event->getResponse()->headers->addCacheControlDirective('no-store');
        $event->getResponse()->headers->addCacheControlDirective('no-cache');
        $event->getResponse()->headers->addCacheControlDirective('must-revalidate');
        $event->getResponse()->headers->set('Pragma', 'no-cache');
        $event->getResponse()->headers->set('Expires', '0');
    }
}
