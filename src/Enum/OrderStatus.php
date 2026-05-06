<?php

namespace App\Enum;

enum OrderStatus: string
{
    case CREATED = 'created';
    case DELIVERY_CHOICE = 'delivery_choice';
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case TO_PREPARE = 'to_prepare';
    case PENDING_SHIPPING = 'pending_shipping';
    case SHIPPING = 'shipping';
    case SHIPPED = 'shipped';
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
            self::TO_PREPARE => 'A préparer',
            self::PENDING_SHIPPING => 'En attente de livraison',
            self::SHIPPING => 'Livraison',
            self::SHIPPED => 'Livrée',
            self::PENDING_REFUND => 'En attente de remboursement',
            self::REFUND => 'Remboursée',
            self::REFUND_DECLINED => 'Remboursement décliné',
            self::CANCELED => 'Annulée',
        };
    }

    public function isAtLeast(self $minimum): bool
    {
        $order = [
            self::CREATED,
            self::DELIVERY_CHOICE,
            self::PENDING_PAYMENT,
            self::PAID,
            self::TO_PREPARE,
            self::PENDING_SHIPPING,
            self::SHIPPING,
            self::SHIPPED,
            self::PENDING_REFUND,
            self::REFUND,
            self::REFUND_DECLINED,
            self::CANCELED,
        ];

        return array_search($this, $order) >= array_search($minimum, $order);
    }
}
