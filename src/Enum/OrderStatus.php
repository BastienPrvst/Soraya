<?php

namespace App\Enum;

enum OrderStatus: string
{
    case CREATED = 'created';
    case DELIVERY_CHOICE = 'delivery_choice';
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case PENDING_SHIPPING = 'pending_shipping';
    case DELIVERED = 'delivered';
    case PENDING_REFUND = 'refund_pending';
    case REFUND = 'refund';
    case REFUND_DECLINED = 'refund_declined';
    case CANCELED = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Crée',
            self::DELIVERY_CHOICE => 'Choix de livraison',
            self::PENDING_PAYMENT => 'En attente de paiement',
            self::PAID => 'Payée',
            self::PENDING_SHIPPING => 'En attente de livraison',
            self::DELIVERED => 'Livrée',
            self::PENDING_REFUND => 'En attente de remboursement',
            self::REFUND => 'Remboursée',
            self::REFUND_DECLINED => 'Remboursement décliné',
            self::CANCELED => 'Annulée',
        };
    }


}
