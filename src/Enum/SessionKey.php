<?php

namespace App\Enum;

enum SessionKey: string
{
    case ORDER_TOKEN   = 'order_token';
    case SHOPPING_CART = 'shopping_cart';
    case SESSION_ID   = 'session_id';
    case DELIVERY_MODE = 'delivery_mode';
}
