<?php

namespace App\Controller;

use App\Enum\SessionElements;
use App\Service\ShoppingCartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

final class ShoppingCartController extends AbstractController
{
    public function __construct(
        private readonly ShoppingCartService $shoppingCartService,
        private readonly RequestStack $requestStack,
    ) {
    }

    #[Route('/shoppingCart/add/{pageId}', name: 'app_shopping_cart_add')]
    public function addToCart(Request $request, int $pageId): Response
    {
        $id = $request->request->get('productId');
        $quantity = (int) $request->request->get('quantity', 1);

        $error = null;
        try {
            $this->shoppingCartService->add($id, $quantity);
        } catch (\LogicException $e) {
            $error = $e->getMessage();
        }

        if ($this->isTurboStreamRequest($request)) {
            return $this->render('shopping_cart/_cart_add.stream.html.twig', [
                'error' => $error,
                'totalQuantityCart' => $this->getTotalQuantityCart(),
            ]);
        }

        if ($error) {
            $this->addFlash('error', $error);
        }

        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_main');
    }

    #[Route(path: '/shoppingCart/update', name: 'app_shopping_cart_update')]
    public function updateCart(Request $request): Response
    {
        $id = $request->request->get('productId');
        $action = filter_var($request->request->get('action'), FILTER_VALIDATE_BOOLEAN);

        $error = null;
        try {
            $this->shoppingCartService->update($id, $action);
        } catch (\LogicException $e) {
            $error = $e->getMessage();
        }

        if ($this->isTurboStreamRequest($request)) {
            return $this->render('shopping_cart/_cart_update.stream.html.twig', $this->buildUpdateContext($id, $error));
        }

        if ($error) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('app_shopping_cart_view');
    }

    #[Route(path: '/shoppingCart/remove', name: 'app_shopping_cart_remove')]
    public function removeFromCart(Request $request): Response
    {
        $id = $request->request->get('productId');
        $this->shoppingCartService->remove($id);

        if ($this->isTurboStreamRequest($request)) {
            return
                $this->render('shopping_cart/_cart_update.stream.html.twig',
                    $this->buildUpdateContext(
                        $id,
                        null,
                        true
                    ));
        }

        return $this->redirectToRoute('app_shopping_cart_view');
    }

    #[Route(path: '/shoppingCart/empty', name: 'app_shopping_cart_empty')]
    public function empty(Request $request): Response
    {
        $this->shoppingCartService->emptyCart();

        if ($this->isTurboStreamRequest($request)) {
            return $this->render('shopping_cart/_cart_empty.stream.html.twig');
        }

        return $this->redirectToRoute('app_shopping_cart_view');
    }

    #[Route(path: '/panier', name: 'app_shopping_cart_view')]
    public function panier(Request $request): Response
    {
        $session = $request->getSession();

        if ($session->has(SessionElements::SHOPPING_CART->value)) {
            $shoppingCart = $session->get(SessionElements::SHOPPING_CART->value);
            $viewCart = $this->shoppingCartService->getCartInformations($shoppingCart);
        }

        $totalCart = $this->shoppingCartService->getCartTotalPrice();

        return $this->render('shopping_cart/shopping_cart.html.twig', [
            'shoppingCart' => $viewCart ?? null,
            'totalCart' => $totalCart,
        ]);
    }

    private function isTurboStreamRequest(Request $request): bool
    {
        if (TurboBundle::STREAM_FORMAT !== $request->getPreferredFormat()) {
            return false;
        }

        $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

        return true;
    }

    private function buildUpdateContext(string $productId, ?string $error, bool $forceRemove = false): array
    {
        $session = $this->requestStack->getSession();
        $rawCart = $session->get(SessionElements::SHOPPING_CART->value, []);
        $viewCart = $this->shoppingCartService->getCartInformations($rawCart);
        $totalCart = $this->shoppingCartService->getCartTotalPrice();

        $stillInCart = !$forceRemove && isset($viewCart[$productId]);

        return [
            'error' => $error,
            'productId' => $productId,
            'stillInCart' => $stillInCart,
            'product' => $stillInCart ? $viewCart[$productId] : null,
            'isEmpty' => empty($viewCart),
            'totalCart' => $totalCart,
            'totalQuantityCart' => array_sum(array_column($viewCart, 'quantity')),
        ];
    }

    private function getTotalQuantityCart(): int
    {
        $session = $this->requestStack->getSession();
        $rawCart = $session->get(SessionElements::SHOPPING_CART->value, []);
        $viewCart = $this->shoppingCartService->getCartInformations($rawCart);

        return array_sum(array_column($viewCart, 'quantity'));
    }
}
