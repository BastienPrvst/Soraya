<?php

namespace App\Controller;

use App\Entity\Order;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Enum\SessionKey;
use App\Form\OrderType;
use App\Service\OrderService;
use App\Service\ShoppingCartService;
use App\Service\StripePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Stripe\Exception\ApiErrorException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Workflow\Registry;

final class PaymentController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(
        private readonly StripePaymentService   $stripePaymentService,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack      $requestStack,
        private readonly ShoppingCartService $shoppingCartService,
        private readonly OrderService $orderService,
        private readonly Registry $registry
    ) {
    }


    /**
     * @throws RandomException
     */
    #[Route(path: '/paiement/auth', name: 'app_payment_auth')]
    public function paymentAuthentification(Request $request): Response
    {
        $session = $this->requestStack->getSession();
        $cart = $session->get(SessionKey::SHOPPING_CART->value, []);
        $products = $this->shoppingCartService->getCartInformations($cart);

        if ($session->has(SessionKey::ORDER_TOKEN->value)) {
            $token = $session->get(SessionKey::ORDER_TOKEN->value);
            $order = $this->entityManager->getRepository(Order::class)->findOneBy(
                [
                    'token' => $token,
                    'status' => OrderStatus::CREATED
                ],
                ['creationDate' => 'DESC']
            );

            if ($order === null) {
                $order = $this->orderService->buildOrder($products, $this->getUser());
            } else {
                $this->orderService->updateOrder($order, $products);
            }
        } else {
            $order = $this->orderService->buildOrder($products, $this->getUser());
            $session->set(SessionKey::ORDER_TOKEN->value, $order->getToken());
        }

//        $workflowState = $this->canTransition($order, 'to_delivery_choice');
//
//        if (!$workflowState) {
//            throw $this->createAccessDeniedException();
//        }


        if ($this->getUser()) {
            return $this->redirectToRoute('app_payment_delivery', [
                'token' => $order->getToken(),
            ]);
        }

        $this->saveTargetPath(
            $request->getSession(),
            'main',
            $this->generateUrl(
                'app_payment_delivery',
                [
                'token' => $order->getToken(),
                ]
            )
        );

        return $this->render('payment/authentification.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route(path: '/paiement/livraison/{token}', name: 'app_payment_delivery')]
    public function paymentDelivery(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
    ): Response {

        $this->verifyOrderIntegrity($order);

//        if ($this->canTransition($order, 'to_delivery_choice')) {
//            $this->applyTransition($order, 'to_delivery_choice');
//        }

        return $this->render('payment/delivery.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route(path: '/paiement/livraison/{type}/{token}', name: 'app_payment_delivery_choice')]
    public function deliveryHome(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
        DeliveryMode $type,
    ): Response {

        $this->verifyOrderIntegrity($order);

        $workflowState = $this->canTransition($order, 'to_pending_payment');

        if (!$workflowState) {
            throw $this->createAccessDeniedException();
        }


        $order->setDeliveryMode($type);
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->applyTransition($order, 'to_pending_payment');

            return $this->redirectToRoute('app_payment_resume', [
                'token' => $order->getToken()
            ]);
        }

        return $this->render('payment/delivery_home.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
        ]);
    }


    #[Route(path: 'paiement/recapitulatif-de-la-commande/{token}', name: 'app_payment_resume')]
    public function paymentResume(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
    ): Response {
        $this->verifyOrderIntegrity($order);

        if (!$this->canTransition($order, 'pay')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('payment/checkout.html.twig', [
            'order' => $order,
        ]);
    }


    /**
     * @throws ApiErrorException
     * @throws \JsonException
     */
    #[Route(path: '/checkout/{token}', name: 'app_stripe_checkout')]
    public function generateSession(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
    ): Response {
        $this->verifyOrderIntegrity($order);
        $clientSecret = $this->stripePaymentService->createPayment($order);

        if (!$clientSecret) {
            return $this->json(['error' => 'Panier vide'], 400);
        }

        return $this->json([
            'clientSecret' => $clientSecret,
        ]);
    }


    #[Route('/confirmation-de-paiement/{token}', name: 'app_stripe_success')]
    public function index(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
    ): Response {

        $this->verifyOrderIntegrity($order);

        $workflowState = $this->canTransition($order, 'pay');

        if (!$workflowState) {
            throw $this->createAccessDeniedException();
        }

        $this->applyTransition($order, 'pay');

        $this->shoppingCartService->emptyCart();

        return $this->render('payment/success.html.twig', []);
    }


    private function verifyOrderIntegrity(Order $order): void
    {
        $this->verifyOrderOwnership($order);
        $session = $this->requestStack->getSession();
        $shoppingCart = $session->get(SessionKey::SHOPPING_CART->value, []);

        $isOrderMatchingCart = $this->orderService->isOrderMatchingCart($order, $shoppingCart);

        if (!$isOrderMatchingCart) {
            $this->orderService->updateOrder($order, $shoppingCart);
        }
    }

    private function verifyOrderOwnership(Order $order): void
    {
        if ($order->getUser()) {
            if ($order->getUser() !== $this->getUser()) {
                throw $this->createAccessDeniedException();
            }
            return;
        }


        $session = $this->requestStack->getSession();
        $token = $session->get(SessionKey::ORDER_TOKEN->value);

        dd($order->getToken(), $token);

        if ($token !== $order->getToken()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function canTransition(Order $order, string $transition): bool
    {
        return $this->registry
            ->get($order, 'order_completing')
            ->can($order, $transition);
    }

    private function applyTransition(Order $order, string $transition): void
    {
        $workflow = $this->registry->get($order, 'order_completing');

        if (!$workflow->can($order, $transition)) {
            throw $this->createAccessDeniedException();
        }

        $workflow->apply($order, $transition);
    }

}
