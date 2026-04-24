<?php

namespace App\Enum;

enum DeliveryMode: string
{
    case RELAY = 'relay';
    case HOME = 'home';


    public function label(): string
    {
        return match ($this) {
            self::RELAY => 'Point Relais',
            self::HOME => 'Domicile',
        };
    }
}
