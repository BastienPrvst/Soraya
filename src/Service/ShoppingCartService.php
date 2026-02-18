<?php

namespace App\Service;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\SessionKey;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Workflow\Registry;

class ShoppingCartService extends AbstractType
{

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly RequestStack      $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly Registry $registry
    ) {
    }

    public function add(string $id, ?int $quantity = 1): void
    {
        $session = $this->requestStack->getSession();
        $product = $this->productRepository->find($id);
        if ($product !== null) {
            if (!$session->has(SessionKey::SHOPPING_CART->value)) {
                $session->set(SessionKey::SHOPPING_CART->value, []);
            }

            $shoppingCart = $session->get(SessionKey::SHOPPING_CART->value);

            if (isset($shoppingCart[$id])) {
                $this->update($id, true, $quantity);
            } else {
                $shoppingCart[$id] = $quantity;
                $session->set(SessionKey::SHOPPING_CART->value, $shoppingCart);
            }
        }
    }

    public function remove(string $id): void
    {
        $session = $this->requestStack->getSession();
        $shoppingCart = $session->get(SessionKey::SHOPPING_CART->value);
        if (isset($shoppingCart[$id])) {
            unset($shoppingCart[$id]);
            $session->set(SessionKey::SHOPPING_CART->value, $shoppingCart);
        }
    }

    public function update(string $id, bool $action, ?int $quantity = 1): void
    {
        $session = $this->requestStack->getSession();
        $shoppingCart = $session->get(SessionKey::SHOPPING_CART->value);

        //Action true = addition
        // False = soustraction

        if ($action) {
            $shoppingCart[$id] += $quantity;
        } else {
            $shoppingCart[$id] -= $quantity;

            if ($shoppingCart[$id] <= 0) {
                unset($shoppingCart[$id]);
            }
        }
        $session->set(SessionKey::SHOPPING_CART->value, $shoppingCart);
    }

    public function emptyCart(): void
    {
        $session = $this->requestStack->getSession();
        if ($session->has(SessionKey::ORDER_TOKEN->value)) {
            $token = $session->get(SessionKey::ORDER_TOKEN->value);
            $order = $this->entityManager->getRepository(Order::class)->findOneBy([
                'token' => $token,
            ]);

            if ($order !== null) {
                $workflow = $this->registry->get($order, 'order_completing');

                if ($workflow->can($order, 'cancel')) {
                    $workflow->apply($order, 'cancel');
                }
            }

            $session->remove(SessionKey::ORDER_TOKEN->value);
        }
        $session->remove(SessionKey::SHOPPING_CART->value);
    }

    public function getQuantity(string $id): int|null
    {
        $session = $this->requestStack->getSession();
        $shoppingCart = $session->get(SessionKey::SHOPPING_CART->value);
        return $shoppingCart[$id] ?? null;
    }

    public function getCartTotalPrice(): float
    {
        $session = $this->requestStack->getSession();

        if (!$session->has(SessionKey::SHOPPING_CART->value)) {
            return 0;
        }

        $shoppingCart = $session->get(SessionKey::SHOPPING_CART->value);
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
        if ($session->has(SessionKey::SHOPPING_CART->value)) {
            $shoppingCart = $session->get(SessionKey::SHOPPING_CART->value);
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
