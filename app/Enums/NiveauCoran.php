<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Le Coran mémorisé. */
enum NiveauCoran: string implements HasLabel
{
    case Aucun = 'aucun';
    case Sourates = 'sourates';
    case Hizb = 'hizb';
    case Hafiz = 'hafiz';

    /** Le Coran compte 60 hizb : au-delà de 59, on est hafiz. */
    public const HIZB_MAX = 59;

    public function getLabel(): string
    {
        return match ($this) {
            self::Aucun => 'Aucun',
            self::Sourates => 'Quelques sourates',
            self::Hizb => 'Un nombre de hizb',
            self::Hafiz => 'Hafiz',
        };
    }
}
