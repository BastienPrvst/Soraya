<?php

namespace App\Enum;

enum PaymentStatus: string
{

    case PENDING = 'pending';
    case SUCCESS = 'success';
    case REFUND = 'refund';

}
