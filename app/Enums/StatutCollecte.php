<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutCollecte: string implements HasColor, HasLabel
{
    case Brouillon = 'brouillon';
    case Ouverte = 'ouverte';
    case Cloturee = 'cloturee';
    case Annulee = 'annulee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Brouillon => 'En préparation',
            self::Ouverte => 'Ouverte',
            self::Cloturee => 'Clôturée',
            self::Annulee => 'Annulée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Brouillon => 'gray',
            self::Ouverte => 'success',
            self::Cloturee => 'warning',
            self::Annulee => 'danger',
        };
    }
}
