<?php

namespace App\DTO;

use App\Entity\Order;

class OrderIntegrityResult
{
    public function __construct(
        public bool $updated,
        public bool $canceled,
        public array $errors,
        public array $cartProducts,
        public ?Order $order
    ) {
    }
}
