<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Enum\DeliveryMode;
use App\Form\OrderType;
use App\Service\DeliveryService;
use App\Service\OrderService;
use App\Service\WorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

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
     * @throws RandomException
     */
    #[Route(path: '/paiement/livraison/{token}', name: 'checkout_delivery', methods: ['POST', 'GET'])]
    public function paymentDelivery(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
        Request $request,
    ): Response {

        if ($this->workflowService->canTransition($order, 'to_delivery_choice')) {
            $this->workflowService->applyTransition($order, 'to_delivery_choice');
        }

        $this->orderService->verifyOrderIntegrity($order);

        $form = $this->createForm(OrderType::class, $order, [
            'delivery_mode' => $order->getDeliveryMode()->value,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $deliveryMode = $form->get('delivery_mode')->getData();

            $this->orderService->refreshSessionKey($order);

            if ($deliveryMode === 'home') {
                //Gestion Livraison
                $order->setDeliveryMode(DeliveryMode::HOME);
                $this->entityManager->persist($order);
                $this->entityManager->flush();

                if ($this->workflowService->canTransition($order, 'to_pending_payment')) {
                    $this->workflowService->applyTransition($order, 'to_pending_payment');
                    return $this->redirectToRoute('checkout_summary', [
                        'token' => $order->getToken(),
                    ]);
                }
            } elseif ($deliveryMode === 'relay') {
                //Gestion Relay

                $relayId = $form->get('relay_id')->getData();

                try {
                    $this->deliveryService->switchDeliverToRelay($order, $relayId);
                } catch (\RuntimeException|\SoapFault) {
                    $this->addFlash('error', 'Veuillez selectionner un point relais valide');

                    return $this->redirectToRoute('checkout_delivery', [
                        'token' => $order->getToken(),
                    ]);
                }

                $this->entityManager->flush();

                if ($this->workflowService->canTransition($order, 'to_pending_payment')) {
                    $this->workflowService->applyTransition($order, 'to_pending_payment');
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
        $this->orderService->verifyOrderIntegrity($order);
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

        $this->orderService->verifyOrderIntegrity($order);
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
}
