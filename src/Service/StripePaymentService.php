<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;

class StripePaymentService extends AbstractController
{

    public function __construct(
        protected RequestStack $requestStack,
        protected LoggerInterface $logger,
        protected OrderRepository $orderRepository,
        protected EntityManagerInterface $entityManager,
        protected Registry $workflowRegistry,
    ) {
    }

    /**
     * @throws ApiErrorException
     * @throws \JsonException
     */
    public function createPayment(Order $order): ?string
    {

        Stripe::setApiKey($this->getParameter('stripe.secret_key'));
        Stripe::setApiVersion('2025-08-27.basil');
        $orderItems = $order->getOrderItems();

        $returnUrl = $this->generateUrl(
            'checkout_success',
            ['token' => $order->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $lineItems = array_values(array_map(static fn(OrderItem $item) => [
            'quantity' => $item->getQuantity(),
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => $item->getProduct() ? $item->getProduct()->getName() : 'Produit',
                ],
                'unit_amount' => (int) round($item->getUnitPrice() * 100),
            ],
        ], $orderItems->toArray()));

        $stripeSession = Session::create([
            'ui_mode' => 'embedded',
            'customer_email' => $order->getEmail(),
            'line_items' => $lineItems,
            'allow_promotion_codes' => true,
            'mode' => 'payment',
            'return_url' => $returnUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'metadata' => [
                'order_token' => $order->getToken(),
            ]
        ]);

        return $stripeSession->client_secret;
    }

    public function handleEvent(Event $event): void
    {
        if ($event->type !== 'checkout.session.completed') {
            return;
        }

        $session = $event->data->object;

        $orderToken = $session->metadata->order_token ?? null;

        if (!$orderToken) {
            return;
        }

        $order = $this->orderRepository->findOneBy(['token' => $orderToken]);

        if (!$order) {
            return;
        }

        if ($order->getStatus() === OrderStatus::PAID) {
            return;
        }

        $workflow = $this->workflowRegistry->get($order, 'order_completing');

        if ($workflow->can($order, 'pay')) {
            $workflow->apply($order, 'pay');
            $this->entityManager->flush();
        }
    }
}
