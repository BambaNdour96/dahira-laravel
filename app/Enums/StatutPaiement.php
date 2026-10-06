<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutPaiement: string implements HasColor, HasLabel
{
    case Declare = 'declare';
    case Confirme = 'confirme';
    case Refuse = 'refuse';

    public function getLabel(): string
    {
        return match ($this) {
            self::Declare => 'À vérifier',
            self::Confirme => 'Confirmé',
            self::Refuse => 'Refusé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Declare => 'warning',
            self::Confirme => 'success',
            self::Refuse => 'danger',
        };
    }
}
