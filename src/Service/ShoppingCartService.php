<?php

namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ShoppingCartService extends AbstractType
{

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly RequestStack      $requestStack
    ) {
    }

    public function add(string $id, ?int $quantity = 1): void
    {
        $session = $this->requestStack->getSession();
        $product = $this->productRepository->find($id);
        if ($product !== null) {
            if (!$session->has('shoppingCart')) {
                $session->set('shoppingCart', []);
            }

            $shoppingCart = $session->get('shoppingCart');

            if (isset($shoppingCart[$id])) {
                $this->update($id, true, $quantity);
            } else {
                $shoppingCart[$id] = $quantity;
                $session->set('shoppingCart', $shoppingCart);
            }
        }
    }

    public function remove(string $id): void
    {
        $session = $this->requestStack->getSession();
        $shoppingCart = $session->get('shoppingCart');
        if (isset($shoppingCart[$id])) {
            unset($shoppingCart[$id]);
            $session->set('shoppingCart', $shoppingCart);
        }
    }

    public function update(string $id, bool $action, ?int $quantity = 1): void
    {
        $session = $this->requestStack->getSession();
        $shoppingCart = $session->get('shoppingCart');

        //Action true = addition
        // False = soustraction
        if ($action === true) {
            $shoppingCart[$id] += $quantity;
        } else {
            $shoppingCart[$id] -= $quantity;

            if ($shoppingCart[$id] <= 0) {
                unset($shoppingCart[$id]);
            }
        }
        $session->set('shoppingCart', $shoppingCart);
    }

    public function emptyCart(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove('shoppingCart');
        session_destroy();
    }

    public function getQuantity(string $id): int|null
    {
        $session = $this->requestStack->getSession();
        $shoppingCart = $session->get('shoppingCart');
        return $shoppingCart[$id] ?? null;
    }

    public function getCartTotalPrice(): float
    {
        $session = $this->requestStack->getSession();

        if (!$session->has('shoppingCart')) {
            return 0;
        }

        $shoppingCart = $session->get('shoppingCart');
        $totalKart = 0;

        foreach ($shoppingCart as $id => $quantity) {
            $product = $this->productRepository->find($id);

            if ($product === null) {
                continue;
            }

            $price = $product->getPrice();
            $totalKart += $quantity * $price;
        }

        return $totalKart;
    }

    public function getTotalQuantity(): int
    {
        $session = $this->requestStack->getSession();
        if ($session->has('shoppingCart')) {
            $shoppingCart = $session->get('shoppingCart');
            return array_sum($shoppingCart);
        }

        return 0;
    }

    public function getCartInformations(array $cart): array
    {
        $updatedCart = [];
        $ids = array_keys($cart);
        $products = $this->productRepository->findBy(['id' => $ids]);

        foreach ($products as $product) {
            if ($product === null) {
                continue;
            }
            $id = $product->getId();
            $quantity = $cart[$id];

            $updatedCart[$id] = [
                'id' => $id,
                'name' => $product->getName(),
                'quantity' => $cart[$id],
                'price' => $product->getPrice(),
                'totalPrice' => $product->getPrice() * $quantity,
                'product' => $product
            ];
        }

        return $updatedCart;
    }
}
