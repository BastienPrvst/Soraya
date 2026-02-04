<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Service\ShoppingCartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

final class ShoppingCartController extends AbstractController
{

    public function __construct(
        private readonly ShoppingCartService $shoppingCartService,
        private readonly ProductRepository   $productRepository,
    ) {
    }

    #[Route('/shoppingCart/add', name: 'app_shopping_cart_add')]
    public function addToCart(Request $request): Response
    {
        $id = $request->request->get('productId');
        $quantity = $request->request->get('quantity');
        $this->shoppingCartService->add($id, $quantity);
        $route = $request->headers->get('referer');
        if ($route === null) {
            return $this->redirectToRoute('app_main');
        }
        return $this->redirect($route);
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

        $session = $request->getSession();
        $shoppingCart = $session->get('shoppingCart', []);

        if (!empty($shoppingCart) &&
            $request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {

            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);

            $product = $this->productRepository->find($id);
            if ($product === null) {
                return $this->redirectToRoute('app_shopping_cart_view');
            }

            $quantity = $this->shoppingCartService->getQuantity($id);
            $totalCart = $this->shoppingCartService->getCartTotalPrice();

            //Si plus de produit en particulier
            if ($quantity === null) {
                return $this->render('shopping_cart/remove.stream.html.twig', [
                    'productId' => $id,
                    'totalCart' => $totalCart,
                ]);
            }

            $productInfo = [
                'id' => $id,
                'quantity' => $quantity,
                'name' => $product->getName(),
                'price' => $product->getPrice(),
                'totalPrice' => $product->getPrice() * $quantity,
            ];

            $totalCart = $this->shoppingCartService->getCartTotalPrice();

            return $this->render('shopping_cart/update.stream.html.twig', [
                'productId' => $id,
                'product' => $productInfo,
                'totalCart' => $totalCart,
            ]);
        }

        return $this->redirectToRoute('app_shopping_cart_view');
    }

    #[Route(path: '/shoppingCart/remove', name: 'app_shopping_cart_remove')]
    public function removeFromCart(Request $request): Response
    {
        $id = $request->request->get('productId');
        $this->shoppingCartService->remove($id);

        if ($request->getPreferredFormat() === TurboBundle::STREAM_FORMAT) {
            $request->setRequestFormat(TurboBundle::STREAM_FORMAT);
            $totalCart = $this->shoppingCartService->getCartTotalPrice();
            return $this->render('shopping_cart/remove.stream.html.twig', [
                'productId' => $id,
                'totalCart' => $totalCart,
            ]);
        }

        return $this->redirectToRoute('app_shopping_cart_view');
    }


    #[Route(path: '/shoppingCart/empty', name: 'app_shopping_cart_empty')]
    public function empty(Request $request): Response
    {
        $this->shoppingCartService->emptyCart();
        $route = $request->headers->get('referer');
        if ($route === null) {
            return $this->redirectToRoute('app_main');
        }
        return $this->redirect($route);
    }

    #[Route(path: '/panier', name: 'app_shopping_cart_view')]
    public function panier(Request $request,): Response
    {
        $session = $request->getSession();

        if ($session->has('shoppingCart')) {
            $shoppingCart = $session->get('shoppingCart');

            $viewCart = [];

            foreach ($shoppingCart as $id => $quantity) {
                $product = $this->productRepository->find($id);

                if ($product === null) {
                    continue;
                }

                $viewCart[$id] = [
                    'id' => $id,
                    'name' => $product->getName(),
                    'quantity' => $quantity,
                    'price' => $product->getPrice(),
                    'totalPrice' => $product->getPrice() * $quantity,
                ];
            }

            $totalCart = $this->shoppingCartService->getCartTotalPrice();
        }

        return $this->render('shopping_cart/shopping_cart.html.twig', [
            'shoppingCart' => $viewCart ?? null,
            'totalCart' => $totalCart ?? null,
        ]);
    }
}
