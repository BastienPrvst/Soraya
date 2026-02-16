<?php

namespace App\EventListener;

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


    /**
     * @param LoginSuccessEvent $event
     * @return void
     *

     * -- Il a une order ACTIVE à son nom
     *
     * ---- Il est en train de commander
     * Idée 2
     * -> On passe l'ancienne commande en canceled et on integre la nouvelle avec le nouveau token
     *
     * ---- Il n'est pas en train de commander
     *
     * -> On ne fait rien
     *
     */

    /** Logique:
     *
     * SI l'utilisateur se connecte/s'inscrit :
     *
     * -- Il n'a pas d'order ACTIVE a son nom
     *
     * ---- Il est en train de commander
     *
     * -> On rattache l'order en cours à son utilisateur, facile
     *
     *
     * ---- Il n'est pas en train de commander
     *
     * On ne fait rien
     */


    #[AsEventListener]
    public function onLoginSuccessEvent(LoginSuccessEvent $event): void
    {
        /* @var $user User */
        $user = $event->getUser();
        $session = $this->requestStack->getSession();
        $oldOrder = $this->orderRepository->findOneBy(
            ['user' => $user, 'status' => OrderStatus::CREATED],
            ['creationDate' => 'DESC']
        );


        if ($session->has(SessionKey::ORDER_TOKEN->value)) {
            $orderToken = $session->get(SessionKey::ORDER_TOKEN->value);

            $order = $this->orderRepository->findOneBy(
                [
                    'token' => $orderToken,
                    'status' => OrderStatus::CREATED,
                ],
                ['creationDate' => 'DESC']
            );

            if ($order && $order->getUser() === null) {
                if ($oldOrder) {
                    $oldOrder->setStatus(OrderStatus::CANCELED);
                }

                $order->setUser($user);
                $this->entityManager->flush();
            }
        } elseif ($oldOrder) {
            $session->remove(SessionKey::SHOPPING_CART->value);
            $session->set(SessionKey::ORDER_TOKEN->value, $oldOrder->getToken());
            $orderItems = $oldOrder->getOrderItems();

            foreach ($orderItems as $item) {
                $this->shoppingCartService->add((string)$item->getProduct()?->getId(), $item->getQuantity());
            }
        }
    }
}
