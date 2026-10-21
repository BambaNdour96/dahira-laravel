<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/** La contribution des membres à un événement, choisie par le super administrateur. */
enum ModeCollecte: string implements HasDescription, HasLabel
{
    case Jayante = 'jayante';
    case Sass = 'sass';

    public function getLabel(): string
    {
        return match ($this) {
            self::Jayante => 'Jàyante',
            self::Sass => 'Sass',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Jayante => 'Chaque membre fixe librement son montant, sans minimum.',
            self::Sass => 'Un minimum imposé, le même pour toute la Daara, fixé par le Jewrine national : chaque membre concerné y est engagé d\'office et peut l\'augmenter.',
        };
    }
}
