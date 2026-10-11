<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/** Comment est fixé l'engagement de chaque membre. */
enum ModeCollecte: string implements HasDescription, HasLabel
{
    case EngagementLibre = 'libre';
    case Minimum = 'minimum';
    case Attribue = 'attribue';
    case Identique = 'identique';

    public function getLabel(): string
    {
        return match ($this) {
            self::EngagementLibre => 'Engagement personnel',
            self::Minimum => 'Minimum fixé par le bureau',
            self::Attribue => 'Montants attribués par le bureau',
            self::Identique => 'Montant identique pour tous',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::EngagementLibre => 'Chaque membre fixe lui-même son engagement. Seul le bureau peut ensuite le modifier.',
            self::Minimum => 'Le bureau fixe un minimum ; chaque membre s\'engage pour ce montant ou davantage.',
            self::Attribue => 'Le bureau attribue un montant à certains membres ; les autres fixent eux-mêmes leur engagement.',
            self::Identique => 'Le même montant pour chaque membre, fixé par le bureau.',
        };
    }
}
