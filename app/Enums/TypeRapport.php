<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TypeRapport: string implements HasLabel
{
    case Suivi = 'suivi';
    case Evaluation = 'evaluation';
    case Ponctuel = 'ponctuel';

    public function getLabel(): string
    {
        return match ($this) {
            self::Suivi => 'Rapport de suivi',
            self::Evaluation => 'Rapport d\'évaluation',
            self::Ponctuel => 'Rapport ponctuel',
        };
    }
}
