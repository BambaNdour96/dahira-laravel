<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** La compréhension de l'arabe. */
enum NiveauComprehension: string implements HasLabel
{
    case NeComprendPas = 'ne_comprend_pas';
    case QuelquesMots = 'quelques_mots';
    case Essentiel = 'essentiel';
    case Maitrise = 'maitrise';

    public function getLabel(): string
    {
        return match ($this) {
            self::NeComprendPas => 'Ne comprend pas',
            self::QuelquesMots => 'Quelques mots',
            self::Essentiel => 'Comprend l\'essentiel',
            self::Maitrise => 'Maîtrise',
        };
    }
}
