<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\DTO\OrderIntegrityResult;
use App\Entity\User;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Repository\ProductRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Random\RandomException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

readonly class OrderService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
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
    public function findLatestOrderOrCreateOne(
        ?string $token,
        string $sessionKey,
        array $cartProducts
    ): OrderIntegrityResult {

        if (empty($cartProducts)) {
            return new OrderIntegrityResult(
                true,
                true,
                ['Impossible de créer une commande, le panier est vide.'],
                [],
                null
            );
        }

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
                $order =
                    $orderRepository->findValidAnonymousOrder(
                        $token,
                        $sessionKey
                    );
            }
        }

        //Si pas de commande trouvée, construction
        if (!$order) {
            return $this->buildOrder($cartProducts);
        }

        //Sinon, update pour mettre à jour les produits
        $indexedProducts = $this->getIndexedProducts($cartProducts);
        $orderIntegrityResult = new OrderIntegrityResult(
            false,
            false,
            [],
            $cartProducts,
            $order
        );
        $orderIntegrityResult = $this->checkStock(
            $cartProducts,
            $indexedProducts,
            $orderIntegrityResult
        );
        return $this->updateOrder(
            $cartProducts,
            $indexedProducts,
            $orderIntegrityResult
        );
    }

    /**
     * @throws RandomException
     * @throws Exception
     */
    public function buildOrder(
        array $cartProducts,
    ): OrderIntegrityResult {
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

        $orderIntegrityResult = new OrderIntegrityResult(
            false,
            false,
            [],
            $cartProducts,
            $order
        );

        if ($user) {
            $address = $this->entityManager->getRepository(Address::class)->findOneBy([
                'user' => $user,
                'isActive' => true
            ]);

            if ($address) {
                $order->setDeliveryAddress($address);
            }
        }

        $indexedProducts = $this->getIndexedProducts($cartProducts);
        $orderIntegrityResult = $this->checkStock(
            $cartProducts,
            $indexedProducts,
            $orderIntegrityResult
        );


        //Création des orderItems + vérification du stock = mise à jour session
        $orderIntegrityResult = $this->updateOrderItems(
            $cartProducts,
            $indexedProducts,
            $orderIntegrityResult
        );

        $orderIntegrityResult->cartProducts = $cartProducts;
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $orderIntegrityResult;
    }

    /**
     * @throws Exception
     * Fonction garde-fou regroupant toutes les autres
     */
    public function verifyOrderIntegrity(
        Order $order,
        array $cartProducts,
        string $token,
        string $sessionKey
    ): OrderIntegrityResult {
        $this->verifyOrderOwnership($order, $token, $sessionKey);

        $orderIntegrityResult = new OrderIntegrityResult(
            false,
            false,
            [],
            $cartProducts,
            $order
        );

        if (empty($cartProducts)) {
            $this->cancelOrder($order);
            $orderIntegrityResult->canceled = true;
            return $orderIntegrityResult;
        }

        $isOrderMatchingCart = $this->isOrderMatchingCart($order, $cartProducts);

        $indexedProducts = $this->getIndexedProducts($cartProducts);
        //Check des stocks par rapport au panier session
        $orderIntegrityResult = $this->checkStock(
            $cartProducts,
            $indexedProducts,
            $orderIntegrityResult
        );

        if ($orderIntegrityResult->canceled === true) {
            return $orderIntegrityResult;
        }

        //Update de l'order si stock != panier OU panier != orderItems
        if (!$isOrderMatchingCart || $orderIntegrityResult->updated === true) {
            $orderIntegrityResult = $this->updateOrder(
                $cartProducts,
                $indexedProducts,
                $orderIntegrityResult
            );
        }

        return $orderIntegrityResult;
    }

    public function verifyOrderOwnership(Order $order, ?string $token, ?string $sessionKey): void
    {
        $user = $this->security->getUser();

        if ($order->getUser() !== null) {
            if (!$user || $order->getUser() !== $user) {
                throw new AccessDeniedException();
            }
            return;
        }

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
    public function updateOrder(
        array $cartProducts,
        array $indexedProducts,
        OrderIntegrityResult $orderIntegrityResult
    ): OrderIntegrityResult {

        if ($orderIntegrityResult->canceled === true) {
            return $orderIntegrityResult;
        }

        //Si panier vide, on annule la commande
        if (empty($cartProducts)) {
            $this->cancelOrder($orderIntegrityResult->order);
            $orderIntegrityResult->canceled = true;
            return $orderIntegrityResult;
        }

        $orderIntegrityResult = $this->updateOrderItems(
            $cartProducts,
            $indexedProducts,
            $orderIntegrityResult
        );

        $this->entityManager->flush();

        return $orderIntegrityResult;
    }

    /**
     * @throws Exception
     */
    private function updateOrderItems(
        array &$cartProducts,
        array $indexedProducts,
        OrderIntegrityResult $orderIntegrityResult
    ): OrderIntegrityResult {

        if ($orderIntegrityResult->canceled === true) {
            return $orderIntegrityResult;
        }

        $cartTotal = 0;
        $order = $orderIntegrityResult->order;
        foreach ($order->getOrderItems() as $orderItem) {
            $this->entityManager->remove($orderItem);
        }

        $order->getOrderItems()->clear();

        foreach ($cartProducts as $productId => $quantity) {
            if ($quantity <= 0) {
                unset($cartProducts[$productId]);
                $orderIntegrityResult->updated = true;
                continue;
            }

            $product = $indexedProducts[$productId] ?? null;

            if (!$product) {
                unset($cartProducts[$productId]);
                $orderIntegrityResult->updated = true;
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

        if (empty($cartProducts)) {
            $this->cancelOrder($order);
            $orderIntegrityResult->canceled = true;
            $orderIntegrityResult->cartProducts = $cartProducts;
            return $orderIntegrityResult;
        }

        $orderIntegrityResult->cartProducts = $cartProducts;

        $order->setTotal($cartTotal);
        return $orderIntegrityResult;
    }

    /**
     * @throws Exception
     */
    private function checkStock(
        array &$cartProducts,
        array $indexedProducts,
        OrderIntegrityResult $orderIntegrityResult
    ): OrderIntegrityResult {

        $errors = [];

        foreach ($cartProducts as $productId => $quantity) {
            $product = $indexedProducts[$productId] ?? null;

            if (!$product) {
                unset($cartProducts[$productId]);
                $orderIntegrityResult->updated = true;
                continue;
            }

            if ($quantity <= 0) {
                unset($cartProducts[$productId]);
                $orderIntegrityResult->updated = true;
                continue;
            }

            $available = $this->stockService->isAvailable($product, $quantity);
            if (!$available) {
                $stock = $product->getStock();
                if ($stock > 0) {
                    $cartProducts[$productId] = $stock;
                    $errors [] =
                        'Le produit '
                        . $product->getName()
                        . ' n\'est pas disponible dans la quantité souhaitée. Stock : '
                        . $stock;
                } else {
                    $errors [] = 'Le produit ' . $product->getName() . ' n\'est pas disponible en stock';
                    unset($cartProducts[$productId]);
                }
                $orderIntegrityResult->updated = true;
            }
        }

        if (empty($cartProducts)) {
            $this->cancelOrder($orderIntegrityResult->order);
            $orderIntegrityResult->cartProducts = $cartProducts;
            $orderIntegrityResult->canceled = true;
            return $orderIntegrityResult;
        }

        $orderIntegrityResult->errors = array_merge($orderIntegrityResult->errors, $errors);
        $orderIntegrityResult->cartProducts = $cartProducts;

        return $orderIntegrityResult;
    }

    /**
     * @throws Exception
     */
    public function cancelOrder(Order $order): void
    {
        if ($this->workflowService->canTransition($order, OrderStatus::CANCELLED->value)) {
            $this->workflowService->applyTransition($order, OrderStatus::CANCELLED->value);
            $this->entityManager->flush();
        }
    }

    private function getIndexedProducts(array $cartProducts): array
    {
        $productIds = array_keys($cartProducts);
        $products = $this->productRepository->findBy(['id' => $productIds]);

        $indexedProducts = [];
        foreach ($products as $product) {
            $indexedProducts[$product->getId()] = $product;
        }

        return $indexedProducts;
    }
}
