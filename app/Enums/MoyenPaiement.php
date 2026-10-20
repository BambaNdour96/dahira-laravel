<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MoyenPaiement: string implements HasLabel
{
    case Wave = 'wave';
    case Especes = 'especes';
    case OrangeMoney = 'orange_money';

    public function getLabel(): string
    {
        return match ($this) {
            self::Wave => 'Wave',
            self::Especes => 'Espèces',
            self::OrangeMoney => 'Orange Money',
        };
    }
}
