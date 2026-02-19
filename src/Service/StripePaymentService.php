<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripePaymentService extends AbstractController
{

    public function __construct(
        protected RequestStack $requestStack,
    ) {
    }

    /**
     * @throws ApiErrorException
     * @throws \JsonException
     */
    public function createPayment(Order $order): ?string
    {

        Stripe::setApiKey($this->getParameter('stripe.secret_key'));
        Stripe::setApiVersion('2025-08-27.basil');
        $orderItems = $order->getOrderItems();

        $returnUrl = $this->generateUrl(
            'app_stripe_success',
            ['token' => $order->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $stripeSession = Session::create([
            'ui_mode' => 'embedded',
            'line_items' => $orderItems
                ->map(static fn (OrderItem $item) => [
                    'quantity' => $item->getQuantity(),
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $item->getProduct()?->getName(),
                        ],
                        'unit_amount' => (int) round($item->getUnitPrice() * 100),
                    ],
                ])
                ->toArray(),
            'mode' => 'payment',
            'return_url' => $returnUrl . '?session_id={CHECKOUT_SESSION_ID}',
        ]);

        return $stripeSession->client_secret;
    }
}
