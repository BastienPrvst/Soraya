<?php

namespace App\Controller;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\SessionElements;
use App\Service\MailerService;
use App\Service\OrderService;
use App\Service\ShoppingCartService;
use App\Service\StripePaymentService;
use App\Service\WorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

final class PaymentController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(
        private readonly StripePaymentService   $stripePaymentService,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack           $requestStack,
        private readonly ShoppingCartService    $shoppingCartService,
        private readonly OrderService           $orderService,
        private readonly LoggerInterface        $logger,
        private readonly WorkflowService        $workflowService,
    ) {
    }

    #[Route(path: 'paiement/récapitulatif-de-la-commande/{token}', name: 'checkout_summary')]
    public function paymentResume(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
    ): Response {
        return $this->render('payment/resume.html.twig', [
            'order' => $order,
            'token' => $order->getToken(),
        ]);
    }

    #[Route(path: '/paiement/{token}', name: 'checkout_pay')]
    public function paymentConfirm(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
    ): Response {
        $this->orderService->verifyOrderOwnership($order);
        $cart = $request->getSession()->get(SessionElements::SHOPPING_CART->value);
        if (!$this->orderService->isOrderMatchingCart($order, $cart)) {
            $this->orderService->updateOrder($order);
            return $this->redirectToRoute('checkout_summary', [
                'token' => $order->getToken(),
                'order' => $order,
            ]);
        }
        $response = $this->render('payment/checkout.html.twig', [
            'order' => $order,
        ]);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }


    /**
     * @throws ApiErrorException
     * @throws \JsonException
     */
    #[Route(path: '/checkout/pay/{token}', name: 'checkout_stripe')]
    public function generateSession(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
    ): Response {
        $this->orderService->verifyOrderOwnership($order);

        if ($order->getStatus() === OrderStatus::PENDING_PAYMENT) {
            $clientSecret = $this->stripePaymentService->createPayment($order);

            if (!$clientSecret) {
                return $this->json(['error' => 'Panier vide'], 400);
            }

            return $this->json([
                'clientSecret' => $clientSecret,
            ]);
        }

        return $this->json(['error' => 'Commande invalide'], 400);
    }


    #[Route('/confirmation-de-paiement/{token}', name: 'checkout_success')]
    public function index(
        #[MapEntity(mapping: ['token' => 'token'])] ?Order $order,
        Request $request,
    ): Response {

        if (!$order) {
            throw $this->createNotFoundException('Commande introuvable');
        }

        if ($order->getStatus() !== OrderStatus::PAID) {
            return $this->render('payment/pending.html.twig', [
                'order' => $order,
                'pollUrl' => $this->generateUrl('check_payment_status', ['token' => $order->getToken()])
            ]);
        }

        $session = $request->getSession();
        if ($session->has(SessionElements::ORDER_TOKEN->value)
            && $session->get(SessionElements::ORDER_TOKEN->value) === $order->getToken()
        ) {
            $this->shoppingCartService->emptyCart();
        }

        $this->orderService->verifyOrderOwnership($order);

        return $this->render('payment/success.html.twig', [
            'order' => $order
        ]);
    }

    #[Route(path: '/check-payment-status/{token}', name: 'check_payment_status')]
    public function paymentStatus(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
    ): JsonResponse {
        return $this->json([
            'status' => $order->getStatus()
        ]);
    }
}
