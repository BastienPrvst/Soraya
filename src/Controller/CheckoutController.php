<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Enum\SessionElements;
use App\Service\MailerService;
use App\Service\OrderService;
use App\Service\ShoppingCartService;
use App\Service\WorkflowService;
use Random\RandomException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly ShoppingCartService $shoppingCartService,
        private readonly OrderService        $orderService,
        private readonly WorkflowService     $workflowService,
    ) {
    }

    use TargetPathTrait;

    /**
     * @throws RandomException
     */
    #[Route('/paiement/back/{token}', name: 'checkout_previous')]
    public function back(
        #[MapEntity(mapping: ['token' => 'token'])] Order $order,
    ): Response {

        if ($this->workflowService->canTransition($order, 'back_to_delivery_choice')) {
            $this->workflowService->applyTransition($order, 'back_to_delivery_choice');
            return $this->redirectToRoute('checkout_delivery', [
                'token' => $order->getToken(),
            ]);
        }

        if ($this->workflowService->canTransition($order, 'back_to_created')) {
            $this->workflowService->applyTransition($order, 'back_to_created');
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
        $cart = $session->get(SessionElements::SHOPPING_CART->value, []);
        $token = $session->get(SessionElements::ORDER_TOKEN->value);
        if (empty($cart)) {
            return $this->redirectToRoute('app_shopping_cart_view');
        }
        $products = $this->shoppingCartService->getCartInformations($cart);

        /* @var Order $order */
        $order = $this->orderService->findLatestOrderOrCreateOne($token, $products);

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

        if ($error !== null && $this->workflowService->canTransition($order, 'to_delivery_choice')) {
            $this->workflowService->applyTransition($order, 'to_delivery_choice');
        }

        return $this->render('payment/authentification.html.twig', [
            'error' => $error,
            'last_username' => $lastUsername,
            'order' => $order,
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
}
