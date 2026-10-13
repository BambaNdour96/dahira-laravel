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
            self::EngagementLibre => 'Jàyante libre',
            self::Minimum => 'Jàyante avec un minimum fixé par le bureau',
            self::Attribue => 'Sass fixé par le bureau (certains membres)',
            self::Identique => 'Sass identique pour tous',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::EngagementLibre => 'Chaque membre fixe lui-même son Jàyante. Seul le bureau peut ensuite le modifier.',
            self::Minimum => 'Le bureau fixe un minimum ; chaque membre fixe son Jàyante, égal ou supérieur.',
            self::Attribue => 'Le bureau fixe le Sass de certains membres ; les autres fixent eux-mêmes leur Jàyante.',
            self::Identique => 'Le même Sass pour chaque membre, fixé par le bureau.',
        };
    }
}
