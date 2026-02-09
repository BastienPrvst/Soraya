<?php

namespace App\Controller;

use App\Service\StripePaymentService;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StripeController extends AbstractController
{
    public function __construct(
        private readonly StripePaymentService $stripePaymentService
    ) {
    }

    #[Route(path: '/paiement', name: 'app_pay')]
    public function pay(): Response
    {
        return $this->render('stripe/checkout.html.twig');
    }

    /**
     * @throws ApiErrorException
     */
    #[Route(path: '/checkout', name: 'app_stripe_checkout')]
    public function generateSession(): Response
    {
        $clientSecret = $this->stripePaymentService->createPayment();

        if (!$clientSecret) {
            return $this->json(['error' => 'Panier vide'], 400);
        }

        return $this->json([
            'clientSecret' => $clientSecret,
        ]);
    }


    #[Route('/confirmation-de-paiement', name: 'app_stripe_success')]
    public function index(): Response
    {
        return $this->render('stripe/success.html.twig', []);
    }
}
