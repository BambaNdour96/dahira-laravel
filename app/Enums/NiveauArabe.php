<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NiveauArabe: string implements HasLabel
{
    case Aucun = 'aucun';
    case Debutant = 'debutant';
    case Intermediaire = 'intermediaire';
    case Avance = 'avance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aucun => 'Aucun',
            self::Debutant => 'Débutant',
            self::Intermediaire => 'Intermédiaire',
            self::Avance => 'Avancé',
        };
    }
}
