<?php

namespace App\Enum;

enum OrderStatus: string
{
    case CREATED = 'created';
    case DELIVERY_CHOICE = 'delivery_choice';
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case REFUND_PENDING = 'refund_pending';
    case REFUND = 'refund';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Crée',
            self::DELIVERY_CHOICE => 'Choix de livraison',
            self::PENDING_PAYMENT => 'En attente de paiement',
            self::PAID => 'Payée',
            self::SHIPPED => 'En cours de livraison',
            self::DELIVERED => 'Livrée',
            self::REFUND_PENDING => 'En attente de remboursement',
            self::REFUND => 'Remboursée',
            self::CANCELED => 'Annulée'
        };
    }


}
