<?php

namespace App\Service;

use App\DTO\OrderIntegrityResult;
use App\Entity\Order;
use App\Enum\SessionElements;
use Symfony\Component\HttpFoundation\Request;

readonly class OrderIntegrityManager
{
    public function __construct(
        private OrderService $orderService
    ) {
    }

    /**
     * @throws \Exception
     */
    public function manage(Order $order, Request $request): OrderIntegrityResult
    {
        $session = $request->getSession();
        $cartProducts = $session->get(SessionElements::SHOPPING_CART->value);
        $token = $session->get(SessionElements::ORDER_TOKEN->value);
        $sessionKey = $session->get(SessionElements::SESSION_KEY->value);

        if (empty($cartProducts)) {
            return new OrderIntegrityResult(
                false,
                true,
                [],
                [],
                null
            );
        }


        $orderIntegrityResult = $this->orderService->verifyOrderIntegrity($order, $cartProducts, $token, $sessionKey);

        if ($orderIntegrityResult->canceled === true) {
            $session->remove(SessionElements::SESSION_KEY->value);
            $session->remove(SessionElements::ORDER_TOKEN->value);
            $session->remove(SessionElements::SHOPPING_CART->value);
            $session->remove(SessionElements::CGU->value);
        }


        if ($orderIntegrityResult->updated === true) {
            $session->set(SessionElements::SHOPPING_CART->value, $orderIntegrityResult->cartProducts);
        }

        return $orderIntegrityResult;
    }
}
