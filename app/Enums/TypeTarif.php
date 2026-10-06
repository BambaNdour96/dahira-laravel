<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TypeTarif: string implements HasLabel
{
    case Adhesion = 'adhesion';
    case Cotisation = 'cotisation';
    case CartePhysique = 'carte_physique';

    public function getLabel(): string
    {
        return match ($this) {
            self::Adhesion => 'Frais de carte de membre',
            self::Cotisation => 'Cotisation',
            self::CartePhysique => 'Carte physique',
        };
    }
}
