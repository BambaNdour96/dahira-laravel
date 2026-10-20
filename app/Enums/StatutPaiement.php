<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatutPaiement: string implements HasColor, HasLabel
{
    case Declare = 'declare';
    case Confirme = 'confirme';
    case Refuse = 'refuse';
    case Contrepasse = 'contrepasse';

    public function getLabel(): string
    {
        return match ($this) {
            self::Declare => 'À vérifier',
            self::Confirme => 'Confirmé',
            self::Refuse => 'Refusé',
            self::Contrepasse => 'Annulé (contre-passé)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Declare => 'warning',
            self::Confirme => 'success',
            self::Refuse, self::Contrepasse => 'danger',
        };
    }
}
