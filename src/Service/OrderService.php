<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Enum\SessionElements;
use App\Repository\ProductRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequestStack           $requestStack,
        private Security               $security,
        private WorkflowService        $workflowService,
        private StockService           $stockService,
        private ProductRepository      $productRepository,
    ) {
    }

    /**
     * @throws RandomException
     * @throws Exception
     */
    public function findLatestOrderOrCreateOne(?string $token, array $cartProducts): ?Order
    {
        $user = $this->security->getUser();
        $orderRepository = $this->entityManager->getRepository(Order::class);
        $order = null;

        //Si le token de session est mis, on récupere la commande liée
        if ($token !== null) {
            if ($user) {
                $order = $orderRepository->findOneBy(
                    [
                        'token' => $token,
                        'status' => [
                            OrderStatus::CREATED,
                            OrderStatus::DELIVERY_CHOICE,
                            OrderStatus::PENDING_PAYMENT
                        ],
                        'user' => $user,
                    ],
                    ['creationDate' => 'DESC']
                );
            } else {
                $session = $this->requestStack->getSession();

                $order =
                    $orderRepository->findValidAnonymousOrder(
                        $token,
                        $session->get(SessionElements::SESSION_KEY->value)
                    );
            }
        }

        //Si pas de commande trouvée, construction
        if (!$order) {
            return $this->buildOrder($cartProducts);
        }
        //Sinon, update pour mettre à jour les produits
        $this->updateOrder($order, $cartProducts);
        return $order;
    }

    /**
     * @throws RandomException
     */
    public function buildOrder(array $cartProducts): Order
    {
        /* @var User $user */
        $user = $this->security->getUser();

        $order = new Order();
        $token = bin2hex(random_bytes(32));
        $sessionKey = bin2hex(random_bytes(32));
        $order
            ->setToken($token)
            ->setSessionKey($sessionKey)
            ->setUser($user)
            ->setFirstname($user?->getFirstname())
            ->setLastname($user?->getLastname())
            ->setPhoneNumber(null)
            ->setCreationDate(new DateTime())
            ->setDelivery(true)
            ->setEmail($user?->getEmail())
            ->setDeliveryMode(DeliveryMode::HOME)
        ;

        if ($user) {
            $address = $this->entityManager->getRepository(Address::class)->findOneBy([
                'user' => $user,
                'isActive' => true
            ]);

            if ($address) {
                $order->setDeliveryAddress($address);
            }
        }

        //Création des orderItems + vérification du stock = mise à jour session
        $isCartModified = $this->createAndUpdateOrderItems($cartProducts, $order);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $session = $this->requestStack->getSession();
        $session->set(SessionElements::ORDER_TOKEN->value, $order->getToken());
        $session->set(SessionElements::SESSION_KEY->value, $order->getSessionKey());

        return $order;
    }

    /**
     * @throws Exception
     * Fonction garde-fou regroupant toutes les autres
     */
    public function verifyOrderIntegrity(Order $order): void
    {
        $this->verifyOrderOwnership($order);
        $session = $this->requestStack->getSession();
        $cartProducts = $session->get(SessionElements::SHOPPING_CART->value, []);

        $isOrderMatchingCart = $this->isOrderMatchingCart($order, $cartProducts);
        if (!$isOrderMatchingCart) {
            $this->updateOrder($order, $cartProducts);
        } else {
            $this->checkStock($cartProducts);
        }
    }

    public function verifyOrderOwnership(Order $order): void
    {
        $user = $this->security->getUser();
        $session = $this->requestStack->getSession();

        if ($order->getUser() !== null) {
            if (!$user || $order->getUser() !== $user) {
                throw new AccessDeniedException();
            }
            return;
        }

        $sessionKey = $session->get(SessionElements::SESSION_KEY->value);
        $token = $session->get(SessionElements::ORDER_TOKEN->value);

        if (!$token || !hash_equals($order->getToken(), $token)) {
            throw new AccessDeniedException();
        }

        if (!$sessionKey || !hash_equals($order->getSessionKey(), $sessionKey)) {
            throw new AccessDeniedException();
        }
    }

    public function isOrderMatchingCart(Order $order, array $cart): bool
    {
        $orderItems = $order->getOrderItems();

        if (count($cart) !== count($orderItems)) {
            return false;
        }

        foreach ($orderItems as $item) {
            $id = $item->getProduct()?->getId();

            if (!isset($cart[$id])) {
                return false;
            }

            if ((int)$cart[$id] !== $item->getQuantity()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws Exception
     * Info :
     */
    public function updateOrder(Order $order, array $cartProducts): bool
    {

        //Si panier vide, on annule la commande
        if (empty($cartProducts)) {
            $this->cancelOrder($order);
            return true;
        }

        //On reconstruit les ordersItems en fonction du panier

        foreach ($order->getOrderItems() as $orderItem) {
            $this->entityManager->remove($orderItem);
        }

        $order->getOrderItems()->clear();

        $updated = $this->createAndUpdateOrderItems($cartProducts, $order);

        $this->entityManager->flush();

        return $updated;
    }

    private function createAndUpdateOrderItems(array &$cartProducts, Order $order): bool
    {
        $updated = false;

        $cartTotal = 0;
        $productIds = array_keys($cartProducts);
        $products = $this->productRepository->findBy(['id' => $productIds]);

        $this->checkStock($cartProducts);

        $indexedProducts = [];
        foreach ($products as $product) {
            $indexedProducts[$product->getId()] = $product;
        }

        foreach ($cartProducts as $productId => $quantity) {
            if ($quantity <= 0) {
                unset($cartProducts[$productId]);
                $updated = true;
                continue;
            }

            $product = $indexedProducts[$productId] ?? null;

            if (!$product) {
                unset($cartProducts[$productId]);
                $updated = true;
                continue;
            }

            $orderItem = new OrderItem();

            $orderItem
                ->setProduct($product)
                ->setQuantity($quantity)
                ->setOrder($order)
                ->setUnitPrice($product->getPrice())
                ->setTotal($product->getPrice() * $quantity)
            ;

            $order->addOrderItem($orderItem);
            $this->entityManager->persist($orderItem);

            $cartTotal += $orderItem->getTotal();
        }

        $order->setTotal($cartTotal);

        return $updated;
    }

    public function checkStock(array &$cartProducts): bool
    {
        $updated = false;
        $productIds = array_keys($cartProducts);
        $products = $this->productRepository->findBy(['id' => $productIds]);

        $indexedProducts = [];
        foreach ($products as $product) {
            $indexedProducts[$product->getId()] = $product;
        }

        foreach ($cartProducts as $productId => $quantity) {
            $product = $indexedProducts[$productId] ?? null;

            if (!$product) {
                unset($cartProducts[$productId]);
                $updated = true;
                continue;
            }

            $available = $this->stockService->isAvailable($product, $quantity);
            if (!$available) {
                $stock = $product->getStock();
                if ($stock > 0) {
                    $cartProducts[$productId] = $stock;
                } else {
                    unset($cartProducts[$productId]);
                }
                $updated = true;
            }
        }

        if ($updated) {
            $session = $this->requestStack->getSession();
            $session->set(SessionElements::SHOPPING_CART->value, $cartProducts);
            $session->getFlashBag()->add(
                'warning',
                'Certains articles n\'étaient plus disponibles dans les quantités demandées.
                 Le panier a été mis à jour.'
            );
        }

        return $updated;
    }

    /**
     * @throws Exception
     */
    public function cancelOrder(Order $order): void
    {
        if ($this->workflowService->canTransition($order, OrderStatus::CANCELLED->value)) {
            $this->workflowService->applyTransition($order, OrderStatus::CANCELLED->value);

            foreach ($order->getOrderItems() as $orderItem) {
                $this->entityManager->remove($orderItem);
            }
            $order->getOrderItems()->clear();
            $order->setTotal(0);

            $session = $this->requestStack->getSession();
            $session->remove(SessionElements::SESSION_KEY->value);
            $session->remove(SessionElements::ORDER_TOKEN->value);
            $session->remove(SessionElements::SHOPPING_CART->value);
            $session->remove(SessionElements::CGU->value);
            $this->entityManager->flush();
        }
    }
}
