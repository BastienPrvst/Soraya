<?php

namespace App\EventListener;

use App\Entity\Address;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\SessionKey;
use App\Repository\OrderRepository;
use App\Service\ShoppingCartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class LoginListener
{

    public function __construct(
        private OrderRepository        $orderRepository,
        private RequestStack           $requestStack,
        private EntityManagerInterface $entityManager,
        private ShoppingCartService $shoppingCartService
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccessEvent(LoginSuccessEvent $event): void
    {
        /* @var $user User */
        $user = $event->getUser();
        $session = $this->requestStack->getSession();
        $oldOrder = $this->orderRepository->findOneBy(
            [
                'user' => $user,
                'status' => [
                    OrderStatus::CREATED,
                    OrderStatus::DELIVERY_CHOICE,
                    OrderStatus::PENDING_PAYMENT
                ]
            ],
            ['creationDate' => 'DESC']
        );
        /**
         * Si l'utilisateur a commencé un panier sans faire d'order et se connecte,
         * sa derniere order avec un statut en cours passe en canceled
        */
        if ($oldOrder
            && !$session->has(SessionKey::ORDER_TOKEN->value)
            && $session->has(SessionKey::SHOPPING_CART->value)) {
                $oldOrder->setStatus(OrderStatus::CANCELED);
                $this->entityManager->flush();
                return;
        }

        /**
         * Si la session possede un token d'order
         */
        if ($session->has(SessionKey::ORDER_TOKEN->value)) {
            $orderToken = $session->get(SessionKey::ORDER_TOKEN->value);

            $order = $this->orderRepository->findOneBy(
                [
                    'token' => $orderToken,
                    'status' => OrderStatus::CREATED,
                ],
                ['creationDate' => 'DESC']
            );

            /**
             * On rattache la personne anonyme qui a commencé une order
             * puis s'est connecté et s'il y'en a une ancienne, on l'annule
             */

            if ($order && $order->getUser() === null) {
                if ($oldOrder) {
                    $oldOrder->setStatus(OrderStatus::CANCELED);
                }

                $order
                    ->setFirstname($user->getFirstname())
                    ->setLastname($user->getLastname())
                    ->setEmail($user->getEmail())
                    ->setUser($user);

                $address = $this->entityManager->getRepository(Address::class)->findOneBy([
                    'User' => $user,
                    'isActive' => true
                ]);
                if ($address) {
                    $order->setDeliveryAddress($address);
                }
                $this->entityManager->flush();
            }

            /**
             * Sinon, on recupere la derniere order et on met à jour la session avec
             */

        } elseif ($oldOrder && $oldOrder->getOrderItems()->count() > 0) {
            $session->remove(SessionKey::SHOPPING_CART->value);
            $session->set(SessionKey::ORDER_TOKEN->value, $oldOrder->getToken());
            $orderItems = $oldOrder->getOrderItems();

            foreach ($orderItems as $item) {
                $this->shoppingCartService->add((string)$item->getProduct()?->getId(), $item->getQuantity());
            }
        }
    }
}
