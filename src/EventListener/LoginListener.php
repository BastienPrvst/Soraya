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
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccessEvent(LoginSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();
        $session = $this->requestStack->getSession();

        $token = $session->get(SessionKey::ORDER_TOKEN->value);

        if (!$token) {
            return;
        }

        $order = $this->orderRepository->findOneBy([
            'token' => $token,
            'status' => [
                OrderStatus::CREATED,
                OrderStatus::DELIVERY_CHOICE,
                OrderStatus::PENDING_PAYMENT
            ],
        ]);

        if (!$order || $order->getUser() !== null) {
            return;
        }

        $order
            ->setUser($user)
            ->setFirstname($user->getFirstname())
            ->setLastname($user->getLastname())
            ->setEmail($user->getEmail());

        $this->entityManager->flush();
    }
}
