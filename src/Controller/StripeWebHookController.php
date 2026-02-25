<?php

namespace App\Controller;

use App\Service\StripePaymentService;
use Psr\Log\LoggerInterface;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StripeWebHookController extends AbstractController
{
    public function __construct(
        readonly private StripePaymentService $stripePaymentService,
        readonly private LoggerInterface $logger,
    ) {
    }

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

        $this->stripePaymentService->handleEvent($event);

        return new Response('OK', 200);
    }
}
