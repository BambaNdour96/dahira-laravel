<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Les sortes de caisses. La banque est prévue : le super administrateur la crée quand le compte est ouvert. */
enum TypeCaisse: string implements HasLabel
{
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';
    case Especes = 'especes';
    case Representant = 'representant';
    case Banque = 'banque';

    public function getLabel(): string
    {
        return match ($this) {
            self::Wave => 'Wave',
            self::OrangeMoney => 'Orange Money',
            self::Especes => 'Espèces (caisse générale)',
            self::Representant => 'Caisse d\'un représentant',
            self::Banque => 'Banque',
        };
    }
}
