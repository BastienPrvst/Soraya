<?php

namespace App\Enum;

enum SessionElements: string
{
    case ORDER_TOKEN   = 'order_token';
    case SHOPPING_CART = 'shopping_cart';
    case SESSION_KEY   = 'session_key';
    case DELIVERY_MODE = 'delivery_mode';
    case CGU = 'CGU';
}
