<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Enum\OrderStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\Registry;

readonly class StripePaymentService
{

    public function __construct(
        private string                 $stripeSecretKey,
        private LoggerInterface        $logger,
        private OrderRepository        $orderRepository,
        private EntityManagerInterface $entityManager,
        private Registry               $workflowRegistry,
        private UrlGeneratorInterface  $urlGenerator,
        private ShoppingCartService $shoppingCartService,
        private MailerService $mailerService,
    ) {
    }

    /**
     * @throws ApiErrorException
     */
    public function createPayment(Order $order): ?string
    {
        Stripe::setApiKey($this->stripeSecretKey);
        Stripe::setApiVersion('2026-02-25.clover');

        if ($order->getPayment() &&
            $order->getPayment()->getStatus() === PaymentStatus::SUCCESS
        ) {
            throw new \LogicException('Paiement déjà effectué.');
        }

        if ($order->getPayment() && $order->getPayment()->getProviderId() !== null) {
            $retrievedStripeSession = Session::retrieve($order->getPayment()->getProviderId());
            if ($retrievedStripeSession && $retrievedStripeSession->status === 'open') {
                $retrievedStripeSession->expire();
            }
            $payment = $order->getPayment();
        } else {
            $payment = new Payment();
        }

        $orderItems = $order->getOrderItems();

        $returnUrl = $this->urlGenerator->generate(
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

        if ($order->getDeliveryPrice() > 0) {
            $lineItems[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Frais de livraison',
                    ],
                    'unit_amount' => (int) round($order->getDeliveryPrice() * 100),
                ],
            ];
        }



        $stripeSession = Session::create([
            'ui_mode' => 'embedded',
            'customer_email' => $order->getEmail(),
            'customer_creation' => 'always',
            'line_items' => $lineItems,
            'allow_promotion_codes' => true,
            'mode' => 'payment',
            'return_url' => $returnUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'metadata' => [
                'order_token' => $order->getToken(),
            ],
        ]);

        $payment
            ->setRelatedOrder($order)
            ->setProvider(PaymentProvider::STRIPE)
            ->setProviderId($stripeSession->id)
            ->setAmount($order->getTotal())
            ->setStatus(PaymentStatus::PENDING)
        ;

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $stripeSession->client_secret;
    }

    public function handleEvent(Event $event): void
    {
        $this->logger->critical($event);
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
        try {
            if ($workflow->can($order, 'pay')) {
                $order->getPayment()?->setStatus(PaymentStatus::SUCCESS);
                $workflow->apply($order, 'pay');
                $this->entityManager->flush();
            }
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage());
            return;
        }
    }
}
