<?php

namespace App\Controller;

use App\Enum\SessionElements;
use App\Repository\ProductRepository;
use App\Service\ShoppingCartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ShoppingCartController extends AbstractController
{

    public function __construct(
        private readonly ShoppingCartService $shoppingCartService,
    ) {
    }

    #[Route('/shoppingCart/add/{pageId}', name: 'app_shopping_cart_add')]
    public function addToCart(Request $request, int $pageId): Response
    {
        $id = $request->request->get('productId');
        $quantity = $request->request->get('quantity');
        $this->shoppingCartService->add($id, $quantity);

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
        $action = filter_var(
            $request->request->get('action'),
            FILTER_VALIDATE_BOOLEAN
        );
        $this->shoppingCartService->update($id, $action);
        return $this->redirectToRoute('app_shopping_cart_view');
    }

    #[Route(path: '/shoppingCart/remove', name: 'app_shopping_cart_remove')]
    public function removeFromCart(Request $request): Response
    {
        $id = $request->request->get('productId');
        $this->shoppingCartService->remove($id);
        return $this->redirectToRoute('app_shopping_cart_view');
    }


    #[Route(path: '/shoppingCart/empty', name: 'app_shopping_cart_empty')]
    public function empty(): Response
    {
        $this->shoppingCartService->emptyCart();
        return $this->redirectToRoute('app_shopping_cart_view');
    }

    #[Route(path: '/panier', name: 'app_shopping_cart_view')]
    public function panier(Request $request,): Response
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
}
