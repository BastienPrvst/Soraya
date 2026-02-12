<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Order;
use App\Form\AdressType;
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
    ) {
    }


    /**
     * @throws RandomException
     */
    #[Route(path: '/paiement/auth', name: 'app_payment_auth')]
    public function paymentAuthentification(Request $request): Response
    {
        $session = $this->requestStack->getSession();
        $cart = $session->get('shoppingCart', []);
        $products = $this->shoppingCartService->getCartInformations($cart);

        if ($session->has('order_token')) {
            $token = $session->get('order_token');
            $order = $this->entityManager->getRepository(Order::class)->findOneBy(['token' => $token]);

            if ($order === null) {
                $order = $this->orderService->buildOrder($products, $this->getUser());
            }
        } else {
            $order = $this->orderService->buildOrder($products, $this->getUser());
            $session->set('order_token', $order->getToken());
        }


        if ($this->getUser()) {
            return $this->render('payment/delivery.html.twig');
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



        $session = $request->getSession();

        if ($request->isMethod('POST')) {
            $deliveryMode = $request->request->get('deliveryMode');

            if (in_array($deliveryMode, ['home', 'relay'], true)) {
                $session->set('deliveryMode', $deliveryMode);
            }

            return $this->redirectToRoute('app_payment_delivery', [
                'token' => $order->getToken(),
            ]);
        }

        $deliveryMode = $session->get('deliveryMode');

        if ($deliveryMode === 'home') {
            $session->set('deliveryMode', 'home');
            if ($this->getUser()) {
                $address = $this->entityManager->getRepository(Address::class)->findOneBy([
                    'user' => $this->getUser(),
                    'isActive' => true
                ]);
            } else {
                $address = new Address();
            }

            $form = $this->createForm(AdressType::class, $address);
            $form->handleRequest($request);

            if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
                $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

                return $this->render('payment/delivery_form.stream.html.twig', [
                    'form' => $form,
                ]);
            }

            return $this->render('payment/delivery.html.twig', [
                'order' => $order,
                'form' => $form,
            ]);
        }


        if ($deliveryMode === 'relay') {
            $session->set('deliveryMode', 'relay');
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
