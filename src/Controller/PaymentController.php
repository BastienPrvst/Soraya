<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\User;
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

final class PaymentController extends AbstractController
{
    use TargetPathTrait;

    public function __construct(
        private readonly StripePaymentService   $stripePaymentService,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack      $requestStack,
        private readonly ShoppingCartService $shoppingCartService,
        private readonly OrderService $orderService,
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

        return $this->render('payment/delivery.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route(path: '/paiement/livraison/home/{token}', name: 'app_payment_delivery_home')]
    public function deliveryHome(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
    ): Response {

        $order->setDeliveryMode(DeliveryMode::HOME);
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('app_payment_resume', [
                'token' => $order->getToken()
            ]);
        }

        return $this->render('payment/delivery_home.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }

    #[Route(path: '/paiement/livraison/relay/{token}', name: 'app_payment_delivery_relay')]
    public function deliveryRelay(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
    ): Response {

        $order->setDeliveryMode(DeliveryMode::RELAY);
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return $this->redirectToRoute('app_payment_resume', [
                'token' => $order->getToken()
            ]);
        }

        return $this->render('payment/delivery_relay.html.twig', [
            'order' => $order,
            'form' => $form,
        ]);
    }



    #[Route(path: '/Recapitulatif-de-la-commande/{token}', name: 'app_payment_resume')]
    public function paymentResume(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
    ): Response {

        return $this->render('payment/resume.html.twig', [
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


        $clientSecret = $this->stripePaymentService->createPayment($order);

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
        return $this->render('payment/success.html.twig', []);
    }
}
