<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Service\ShoppingCartService;
use App\Service\StripePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymentController extends AbstractController
{

    public function __construct(
        private readonly StripePaymentService   $stripePaymentService,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack      $requestStack,
        private readonly ShoppingCartService $shoppingCartService
    ) {
    }

    #[Route(path: '/création-de-la-commande', name: 'app_order_prepare')]
    public function prepareOrder(): Response
    {
        $session = $this->requestStack->getSession();
        if (!$session->has("shoppingCart")) {
            return $this->redirectToRoute('app_shopping_cart_view');
        }

        $shoppingCart = $session->get('shoppingCart');

        $products = $this->shoppingCartService->getCartInformations($shoppingCart);

        $order = new Order();
        $order
            ->setUser($this->getUser())
            ->setCreationDate(new \DateTime())
            ->setDelivery(true);

        $cartTotal = 0;

        foreach ($products as $product) {
            $orderItem = new OrderItem();
            $orderItem
                ->setProduct($product['product'])
                ->setQuantity($product['quantity'])
                ->setRelatedOrder($order)
                ->setUnitPrice($product['price'])
                ->setTotal($product['price'] * $product['quantity']);

            $order->addOrderItem($orderItem);
            $this->entityManager->persist($orderItem);

            $cartTotal += $orderItem->getTotal();
        }

        $order->setTotal($cartTotal);
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        dd($order->getOrderItems());

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
