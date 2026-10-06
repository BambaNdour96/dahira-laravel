<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutCarte: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Revoquee = 'revoquee';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Revoquee => 'Révoquée',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Revoquee => 'danger',
        };
    }
}
