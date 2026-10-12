<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutProjet: string implements HasColor, HasLabel
{
    case Preparation = 'preparation';
    case EnCours = 'en_cours';
    case Suspendu = 'suspendu';
    case Termine = 'termine';
    case Abandonne = 'abandonne';

    public function getLabel(): string
    {
        return match ($this) {
            self::Preparation => 'En préparation',
            self::EnCours => 'En cours',
            self::Suspendu => 'Suspendu',
            self::Termine => 'Terminé',
            self::Abandonne => 'Abandonné',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Preparation => 'gray',
            self::EnCours => 'info',
            self::Suspendu => 'warning',
            self::Termine => 'success',
            self::Abandonne => 'danger',
        };
    }

    public function estClos(): bool
    {
        return $this === self::Termine || $this === self::Abandonne;
    }
}
