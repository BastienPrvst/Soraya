<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\User;
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
                    'status' => 'created'
                ]
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
        Request $request,
    ): Response {

        if ($request->isMethod('POST')) {
            $deliveryMode = $request->request->get(SessionKey::DELIVERY_MODE->value);

            if ($deliveryMode === 'home') {
                /* @var $user User */
                $user = $this->getUser();

                if ($user) {
                    $address = $this->entityManager->getRepository(Address::class)->findOneBy([
                        'User' => $user,
                        'isActive' => true
                    ]) ?? new Address();
                } else {
                    $address = new Address();
                }


                if ($user) {
                    $order
                        ->setFirstname($user->getFirstname())
                        ->setLastname($user->getLastname())
                        ->setEmail($user->getEmail())
                        ->setDeliveryAddress($address)
                    ;
                }


                $form = $this->createForm(OrderType::class, $order);

                return $this->render('payment/_delivery_home.html.twig', [
                    'form' => $form->createView(),
                    'order' => $order,
                ]);
            }

            if ($deliveryMode === 'relay') {
                return $this->render('payment/_delivery_relay.html.twig', [
                    'order' => $order,
                ]);
            }

            return new Response('', 204);
        }

        return $this->render('payment/delivery.html.twig', [
            'order' => $order,
        ]);
    }


    /**
     * @throws ApiErrorException
     * @throws \JsonException
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
        return $this->render('payment/success.html.twig', []);
    }
}
