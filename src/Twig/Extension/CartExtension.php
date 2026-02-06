<?php

namespace App\Twig\Extension;

use App\Service\ShoppingCartService;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class CartExtension extends AbstractExtension implements GlobalsInterface
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
