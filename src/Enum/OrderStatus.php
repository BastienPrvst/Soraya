<?php

namespace App\Enum;

enum OrderStatus: string
{
    case CREATED = 'created';
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Crée',
            self::PENDING_PAYMENT => 'En attente de paiement',
            self::PAID => 'Payée',
            self::SHIPPED => 'En cours de livraison',
            self::DELIVERED => 'Livrée',
            self::CANCELED => 'Annulée'
        };
    }


}
