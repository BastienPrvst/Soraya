<?php

namespace App\Enum;

enum DeliveryMode: string
{
    case RELAY = 'relay';
    case HOME = 'home';
}
