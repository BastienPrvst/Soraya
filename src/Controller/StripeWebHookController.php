<?php

namespace App\Controller;

use App\Service\OrderFinalizer;
use App\Service\StripePaymentService;
use Psr\Log\LoggerInterface;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class StripeWebHookController extends AbstractController
{
    public function __construct(
        readonly private StripePaymentService $stripePaymentService,
        readonly private LoggerInterface $logger,
        readonly private OrderFinalizer $orderFinalizer,
        readonly private EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws \Throwable
     */
    #[Route('/webhook', name: 'stripe_webhook')]
    public function webhook(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('stripe-signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $this->getParameter('stripe.webhook_secret_key')
            );
        } catch (\Throwable $e) {
            $this->logger->error('Stripe webhook signature error', [
                'exception' => $e->getMessage()
            ]);
            return new Response('Invalid signature', 400);
        }

        $order = $this->stripePaymentService->handleEvent($event);

        if (null !== $order) {
            $this->dispatcher->addListener(
                KernelEvents::TERMINATE,
                function () use ($order) {
                    try {
                        $this->orderFinalizer->finalize($order);
                    } catch (\Throwable $e) {
                        $this->logger->error('Finalisation commande échouée', [
                            'order' => $order->getId(),
                            'exception' => $e,
                        ]);
                    }
                }
            );
        }

        return new Response('OK', 200);
    }
}
