<?php

namespace App\Controller;

use App\DTO\OrderIntegrityResult;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\SessionElements;
use App\Service\MailerService;
use App\Service\OrderService;
use App\Service\ShoppingCartService;
use App\Service\StripePaymentService;
use App\Service\WorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
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

    /**
     * @throws Exception
     * FallBack si changement order
     */
    #[Route(path: 'paiement/récapitulatif-de-la-commande/{token}', name: 'checkout_summary')]
    public function paymentResume(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        RateLimiterFactoryInterface $checkoutLimiter,
        Request $request
    ): Response {
        $limiter = $checkoutLimiter->create($request->getClientIp() . '_' . $order->getToken());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $integrityInfos = $this->orderService->verifyOrderIntegrity($order);
        $this->manageIntegrityInfos($integrityInfos, $request);
        if ($integrityInfos->canceled === true) {
            return $this->redirectToRoute('app_main');
        }

        return $this->render('payment/resume.html.twig', [
            'order' => $order,
            'token' => $order->getToken(),
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/paiement/{token}', name: 'checkout_pay')]
    public function paymentConfirm(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        RateLimiterFactoryInterface $checkoutLimiter,
        Request $request
    ): Response {

        $limiter = $checkoutLimiter->create($request->getClientIp() . '_' . $order->getToken());

        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        $integrityInfos = $this->orderService->verifyOrderIntegrity($order);
        $this->manageIntegrityInfos($integrityInfos, $request);

        if ($integrityInfos->canceled === true) {
            return $this->redirectToRoute('app_main'); //
        }

        if ($integrityInfos->updated === true) {
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
     * @throws Exception
     */
    #[Route(path: '/checkout/pay/{token}', name: 'checkout_stripe')]
    public function generateSession(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        RateLimiterFactoryInterface $checkoutLimiter,
        Request $request
    ): Response {

        $limiter = $checkoutLimiter->create($request->getClientIp() . '_' . $order->getToken());

        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

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


    /**
     * @throws TransportExceptionInterface
     * @throws ExceptionInterface
     * @throws Exception
     */
    #[Route('/confirmation-de-paiement/{token}', name: 'checkout_success')]
    public function index(
        #[MapEntity(mapping: ['token' => 'token'])] ?Order $order,
        Request $request,
        MailerService $mailerService,
    ): Response {

        if (!$order) {
            throw $this->createNotFoundException('Commande introuvable');
        }

        if (!$order->getStatus()?->isAtLeast(OrderStatus::PENDING_PAYMENT)) {
            throw new BadRequestHttpException('Commande non valide');
        }

        if ($order->getStatus() === OrderStatus::PENDING_PAYMENT) {
            return $this->render('payment/pending.html.twig', [
                'order' => $order,
                'pollUrl' => $this->generateUrl('check_payment_status', ['token' => $order->getToken()])
            ]);
        }

        if ($order->getStatus() === OrderStatus::PAID) {
            if ($this->workflowService->canTransition($order, 'to_pending_delivery')) {
                $this->workflowService->applyTransition($order, 'to_pending_delivery');
                $this->entityManager->flush();
            }
            $mailerService->sendConfirmationEmail($order);
        }

        $session = $request->getSession();
        if ($session->has(SessionElements::ORDER_TOKEN->value)
            && $session->get(SessionElements::ORDER_TOKEN->value) === $order->getToken()
        ) {
            $this->shoppingCartService->emptyCart();
        }

        return $this->render('payment/success.html.twig', [
            'order' => $order
        ]);
    }

    #[Route(path: '/check-payment-status/{token}', name: 'check_payment_status')]
    public function paymentStatus(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        RateLimiterFactoryInterface $paymentStatusLimiter,
        Request $request,
    ): JsonResponse {

        $limiter = $paymentStatusLimiter->create($request->getClientIp() . '_' . $order->getToken());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        return $this->json([
            'status' => $order->getStatus()
        ]);
    }

    private function manageIntegrityInfos(OrderIntegrityResult $orderIntegrityResult, Request $request): void
    {
        $session = $request->getSession();

        if ($orderIntegrityResult->canceled === true) {
            $session->remove(SessionElements::SESSION_KEY->value);
            $session->remove(SessionElements::ORDER_TOKEN->value);
            $session->remove(SessionElements::SHOPPING_CART->value);
            $session->remove(SessionElements::CGU->value);
        }

        $errors = $orderIntegrityResult->errors;
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        if ($orderIntegrityResult->updated === true) {
            $session->set(SessionElements::SHOPPING_CART->value, $orderIntegrityResult->cartProducts);
        }
    }
}
