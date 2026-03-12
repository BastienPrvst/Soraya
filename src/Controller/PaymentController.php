<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\User;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Enum\SessionKey;
use App\Form\OrderType;
use App\Repository\AddressRepository;
use App\Service\DeliveryService;
use App\Service\MailerService;
use App\Service\MondialRelayService;
use App\Service\OrderService;
use App\Service\ShoppingCartService;
use App\Service\StripePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use SoapFault;
use Stripe\Exception\ApiErrorException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\UX\Turbo\TurboBundle;

final class PaymentController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(
        private readonly StripePaymentService   $stripePaymentService,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack      $requestStack,
        private readonly ShoppingCartService $shoppingCartService,
        private readonly OrderService $orderService,
        private readonly Registry $registry,
    ) {
    }

    #[Route('/paiement/back/{token}', name: 'checkout_previous')]
    public function back(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
    ): Response {

        if ($this->canTransition($order, 'back_to_delivery_choice')) {
            $this->applyTransition($order, 'back_to_delivery_choice');

            return $this->redirectToRoute('checkout_delivery', [
                'token' => $order->getToken(),
            ]);
        }

        if ($this->canTransition($order, 'back_to_created')) {
            $this->applyTransition($order, 'back_to_created');

            return $this->redirectToRoute('checkout_auth');
        }

        throw $this->createAccessDeniedException();
    }

    /**
     * @throws RandomException
     */
    #[Route(path: '/paiement/auth', name: 'checkout_auth')]
    public function paymentAuthentification(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        $session = $request->getSession();
        $cart = $session->get(SessionKey::SHOPPING_CART->value, []);
        $token = $session->get(SessionKey::ORDER_TOKEN->value);
        if (empty($cart)) {
            return $this->redirectToRoute('app_shopping_cart_view');
        }
        $products = $this->shoppingCartService->getCartInformations($cart);
        /* @var User $user */
        $user = $this->getUser();

        /* @var Order $order */
        $order = $this->orderService->findLatestOrderOrCreateOne($token, $products, $user);

        if ($this->getUser()) {
            return $this->redirectToRoute('checkout_delivery', [
                'token' => $order->getToken(),
            ]);
        }
        $this->saveTargetPath(
            $request->getSession(),
            'main',
            $this->generateUrl(
                'checkout_delivery',
                [
                    'token' => $order->getToken(),
                ]
            )
        );

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($error !== null && $this->canTransition($order, 'to_delivery_choice')) {
            $this->applyTransition($order, 'to_delivery_choice');
        }

        return $this->render('payment/authentification.html.twig', [
            'error' => $error,
            'last_username' => $lastUsername,
            'order' => $order,
        ]);
    }

    /**
     * @throws SoapFault
     */
    #[Route(path: '/paiement/livraison/{token}', name: 'checkout_delivery', methods: ['POST', 'GET'])]
    public function paymentDelivery(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
        DeliveryService $deliveryService,
    ): Response {
        if ($this->canTransition($order, 'to_delivery_choice')) {
            $this->applyTransition($order, 'to_delivery_choice');
        }

        $this->verifyOrderIntegrity($order);

        $form = $this->createForm(OrderType::class, $order, [
            'delivery_mode' => $order->getDeliveryMode()->value,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $deliveryMode = $form->get('delivery_mode')->getData();


            if ($deliveryMode === 'home') {
                //Gestion Livraison
                $order->setDeliveryMode(DeliveryMode::HOME);
                $this->entityManager->persist($order);
                $this->entityManager->flush();

                if ($this->canTransition($order, 'to_pending_payment')) {
                    $this->applyTransition($order, 'to_pending_payment');
                    return $this->redirectToRoute('checkout_summary', [
                        'token' => $order->getToken(),
                    ]);
                }
            } elseif ($deliveryMode === 'relay') {
                //Gestion Relay

                $relayId = $form->get('relay_id')->getData();

                try {
                    $deliveryService->prepareRelayDelivery($order, $relayId);
                } catch (\RuntimeException) {
                    $this->addFlash('error', 'Veuillez selectionner un point relais valide');

                    return $this->redirectToRoute('checkout_delivery', [
                        'token' => $order->getToken(),
                    ]);
                }

                $this->entityManager->flush();

                if ($this->canTransition($order, 'to_pending_payment')) {
                    $this->applyTransition($order, 'to_pending_payment');
                    return $this->redirectToRoute('checkout_summary', [
                        'token' => $order->getToken(),
                    ]);
                }
            }
        }

        return $this->render('payment/delivery_choice.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
        ]);
    }

    #[Route(path: '/paiement/livraison/home/{token}', name: 'checkout_delivery_home')]
    public function paymentDeliveryForm(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
        DeliveryService $deliveryService,
    ): Response {

        $deliveryService->switchRelayToDeliver($order, $this->getUser());
        $this->entityManager->flush();

        $form = $this->createForm(OrderType::class, $order, [
            'delivery_mode' => 'home'
        ]);
        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            return $this->render('payment/delivery.frame.html.twig', [
                'form' => $form->createView(),
                'order' => $order,
            ]);
        }

        return $this->render('payment/delivery_fallback.html.twig', [
            'form' => $form->createView(),
            'order' => $order,
        ]);
    }

    #[Route(path: '/paiement/livraison/relay/{token}', name: 'checkout_delivery_relay')]
    public function paymentRelayForm(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
    ): Response {

        $order->setDeliveryMode(DeliveryMode::RELAY);
        $this->entityManager->flush();

        $form = $this->createForm(OrderType::class, $order, [
            'delivery_mode' => 'relay'
        ]);

        if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
            return $this->render('payment/relay.frame.html.twig', [
                'order' => $order,
            ]);
        }
        return $this->render('payment/relay_fallback.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
        ]);
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
    ): Response {
        $this->verifyOrderIntegrity($order);
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
        $this->verifyOrderIntegrity($order);

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
        if ($session->has(SessionKey::ORDER_TOKEN->value)
            && $session->get(SessionKey::ORDER_TOKEN->value) === $order->getToken()
        ) {
            $this->shoppingCartService->emptyCart();
        }

        $this->verifyOrderOwnership($order);

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

    #[Route(path: '/renvoie-mail/{token}', name: 'renvoi-mail')]
    public function renvoiMail(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        MailerService $mailerService
    ): Response {
        $mailerService->sendConfirmationEmail($order);
        return $this->render('payment/success.html.twig', [
            'order' => $order
        ]);
    }


    private function verifyOrderIntegrity(Order $order): void
    {
        $this->verifyOrderOwnership($order);
        $session = $this->requestStack->getSession();
        $shoppingCart = $session->get(SessionKey::SHOPPING_CART->value, []);
        $products = $this->shoppingCartService->getCartInformations($shoppingCart);

        $isOrderMatchingCart = $this->orderService->isOrderMatchingCart($order, $products);

        if (!$isOrderMatchingCart) {
            $this->orderService->updateOrder($order, $products);
        }
    }

    private function verifyOrderOwnership(Order $order): void
    {
        $user = $this->getUser();

        if ($order->getUser() !== null) {
            if (!$user || $order->getUser() !== $user) {
                throw $this->createAccessDeniedException();
            }
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
        $this->entityManager->flush();
    }
}
