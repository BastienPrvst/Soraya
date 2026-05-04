<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Order;
use App\Enum\DeliveryMode;
use App\Enum\SessionElements;
use App\Form\AddressType;
use App\Form\DeliveryOrderType;
use App\Form\RelayOrderType;
use App\Service\DeliveryService;
use App\Service\OrderService;
use App\Service\WorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use SoapFault;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class DeliveryController extends AbstractController
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly OrderService $orderService,
        private readonly EntityManagerInterface $entityManager,
        private readonly DeliveryService $deliveryService,
    ) {
    }

    /**
     * @param Order $order
     * @param RateLimiterFactoryInterface $checkoutLimiter
     * @param Request $request
     * @return Response
     */
    #[Route(path: '/paiement/livraison/{token}', name: 'checkout_delivery', methods: ['POST', 'GET'])]
    public function deliveryCreation(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        RateLimiterFactoryInterface $checkoutLimiter,
        Request $request,
    ): Response {

        $limiter = $checkoutLimiter->create($request->getClientIp() . '_' . $order->getToken());
        if (false === $limiter->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }

        if ($this->workflowService->canTransition($order, 'to_delivery_choice')) {
            $this->workflowService->applyTransition($order, 'to_delivery_choice');
        }

        $this->orderService->verifyOrderIntegrity($order);
        if ($order->getDeliveryMode() === DeliveryMode::RELAY) {
            return $this->redirectToRoute(
                'checkout_delivery_relay',
                ['token' => $order->getToken()]
            );
        }

        return $this->redirectToRoute(
            'checkout_delivery_home',
            ['token' => $order->getToken()]
        );
    }

    /**
     * @param Order $order
     * @param Request $request
     * @param DeliveryService $deliveryService
     * @return Response
     */
    #[Route(path: '/paiement/livraison/home/{token}', name: 'checkout_delivery_home')]
    public function paymentDeliveryForm(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
        DeliveryService $deliveryService,
    ): Response {
        $this->orderService->verifyOrderIntegrity($order);
        $deliveryService->switchRelayToDeliver($order, $this->getUser());
        $this->entityManager->flush();

        $form = $this->createForm(DeliveryOrderType::class, $order, [
            'CGU' => $request->getSession()->get(SessionElements::CGU->value) ?? false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $request->getSession()->set(SessionElements::CGU->value, true);

            $order->setDeliveryMode(DeliveryMode::HOME);
            $this->entityManager->persist($order);

            if ($form->has('billingAddress') &&
                $form->get('billingAddress')->getData() === true
            ) {
                $billingAddress = clone $order->getDeliveryAddress();
                $this->entityManager->persist($billingAddress);
                $order->setBillingAddress($billingAddress);
                $this->entityManager->flush();

                if ($this->workflowService->canTransition($order, 'to_pending_payment')) {
                    $this->workflowService->applyTransition($order, 'to_pending_payment');
                    $this->entityManager->flush();
                    return $this->redirectToRoute('checkout_summary', [
                        'token' => $order->getToken()
                    ]);
                }
            }

            $this->entityManager->flush();

            return $this->redirectToRoute('checkout_delivery_billing_address', [
                'token' => $order->getToken(),
            ]);
        }

        return $this->render('payment/delivery_form.html.twig', [
            'form' => $form->createView(),
            'order' => $order,
        ]);
    }

    /**
     * @param Order $order
     * @param Request $request
     * @return Response
     */
    #[Route(path: '/paiement/livraison/relay/{token}', name: 'checkout_delivery_relay')]
    public function paymentRelayForm(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
    ): Response {

        $this->orderService->verifyOrderIntegrity($order);
        $this->deliveryService->switchDeliverToRelay($order);
        $this->entityManager->flush();

        $form = $this->createForm(RelayOrderType::class, $order, [
            'CGU' => $request->getSession()->get(SessionElements::CGU->value) ?? false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $request->getSession()->set(SessionElements::CGU->value, true);
            $relayId = $form->get('relay_id')->getData();

            $order->setDeliveryMode(DeliveryMode::RELAY);

            try {
                $this->deliveryService->createRelayAddress($order, $relayId);
            } catch (RuntimeException|SoapFault $exception) {

                dd($exception->getMessage());
                $this->addFlash('error', 'Veuillez sélectionner un point relais valide');

                return $this->redirectToRoute('checkout_delivery', [
                    'token' => $order->getToken(),
                ]);
            }

            return $this->redirectToRoute('checkout_delivery_billing_address', [
                'token' => $order->getToken()
            ]);
        }

        return $this->render('payment/relay_form.html.twig', [
            'order' => $order,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Order $order
     * @param Request $request
     * @return Response
     */
    #[Route(path: '/paiement/adresse-de-facturation/{token}', name: 'checkout_delivery_billing_address')]
    public function paymentBillingAddressForm(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
    ): Response {
        if ($this->workflowService->canTransition($order, 'back_to_delivery_choice')) {
            $this->workflowService->applyTransition($order, 'back_to_delivery_choice');
        }

        $billingAddress = $order->getBillingAddress();

        if (!$billingAddress) {
            $billingAddress = new Address();
        }

        $form = $this->createForm(AddressType::class, $billingAddress, ['create' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $billingAddress = $form->getData();
            $this->entityManager->persist($billingAddress);
            $order->setBillingAddress($billingAddress);
            $this->entityManager->flush();

            if ($this->workflowService->canTransition($order, 'to_pending_payment')) {
                $this->workflowService->applyTransition($order, 'to_pending_payment');
                return $this->redirectToRoute('checkout_summary', [
                    'token' => $order->getToken(),
                ]);
            }
        }

        return $this->render('payment/billing_address.html.twig', [
           'order' => $order,
           'form' => $form->createView(),
        ]);
    }
}
