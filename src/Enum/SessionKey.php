<?php

namespace App\Enum;

enum SessionKey: string
{
    case ORDER_TOKEN   = 'order_token';
    case SHOPPING_CART = 'shopping_cart';
    case DELIVERY_MODE = 'delivery_mode';
}
