<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Le feu tricolore d'un projet. */
enum SanteProjet: string implements HasColor, HasLabel
{
    case Vert = 'vert';
    case Orange = 'orange';
    case Rouge = 'rouge';
    case Clos = 'clos';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vert => '🟢 Dans les temps',
            self::Orange => '🟠 À surveiller',
            self::Rouge => '🔴 En danger',
            self::Clos => '⚪ Clos',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Vert => 'success',
            self::Orange => 'warning',
            self::Rouge => 'danger',
            self::Clos => 'gray',
        };
    }
}
