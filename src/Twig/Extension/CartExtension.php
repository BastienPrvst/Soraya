<?php

namespace App\Twig\Extension;

use App\Service\ShoppingCartService;
use Twig\Extension\AbstractExtension;

class CartExtension extends AbstractExtension
{
    public function __construct(
        private readonly ShoppingCartService $shoppingCartService
    ) {
    }

    public function getGlobals(): array
    {
        return [
            'totalQuantityCart' => $this->shoppingCartService->getTotalQuantity(),
        ];
    }
}
