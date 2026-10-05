<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Sexe: string implements HasLabel
{
    case Homme = 'H';
    case Femme = 'F';

    public function getLabel(): string
    {
        return match ($this) {
            self::Homme => 'Homme',
            self::Femme => 'Femme',
        };
    }
}
