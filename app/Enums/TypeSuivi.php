<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Les entrées du journal d'un projet. */
enum TypeSuivi: string implements HasColor, HasLabel
{
    case Point = 'point';
    case Risque = 'risque';
    case Decision = 'decision';
    case Depense = 'depense';

    public function getLabel(): string
    {
        return match ($this) {
            self::Point => 'Point d\'avancement',
            self::Risque => 'Risque ou difficulté',
            self::Decision => 'Décision',
            self::Depense => 'Dépense',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Point => 'info',
            self::Risque => 'danger',
            self::Decision => 'success',
            self::Depense => 'warning',
        };
    }
}
