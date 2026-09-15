<?php

namespace App\Twig\Extension;

use App\Service\ShoppingCartService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class CartExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ShoppingCartService $shoppingCartService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getGlobals(): array
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$request->hasSession()) {
            return [
                'totalQuantityCart' => 0,
            ];
        }

        return [
            'totalQuantityCart' => $this->shoppingCartService->getTotalQuantity(),
        ];
    }
}
