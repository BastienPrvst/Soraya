<?php

namespace App\Service;

use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\StripeClient;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripePaymentService extends AbstractType
{

    public function __construct(
        protected RequestStack $requestStack,
        private readonly ShoppingCartService $shoppingCartService
    ) {
    }

    /**
     * @throws ApiErrorException
     * @throws \JsonException
     */
    public function createPayment(): ?string
    {

        Stripe::setApiKey($this->getParameter('payment.secret_key'));
        Stripe::setApiVersion('2025-08-27.basil');

        $session = $this->requestStack->getSession();
        if (!$session->has('shoppingCart')) {
            return null;
        }

        $shoppingCart = $session->get('shoppingCart');
        $cart = $this->shoppingCartService->getCartInformations($shoppingCart);

        if (empty($cart)) {
            return null;
        }

        $returnUrl = $this->generateUrl(
            'app_stripe_success',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $stripeSession = Session::create([
            'ui_mode' => 'embedded',
            'line_items' => array_values(array_map(static fn (array $product) => [
                'quantity' => $product['quantity'],
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $product['name'],
                    ],
                    'unit_amount' => (int) round($product['price'] * 100),
                ],
            ], $cart)),
            'mode' => 'payment',
            'return_url' => $returnUrl . '?session_id={CHECKOUT_SESSION_ID}',
        ]);

        return $stripeSession->client_secret;
    }
}
