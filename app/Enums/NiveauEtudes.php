<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NiveauEtudes: string implements HasLabel
{
    case Aucun = 'aucun';
    case Coranique = 'coranique';
    case Elementaire = 'elementaire';
    case Moyen = 'moyen';
    case Secondaire = 'secondaire';
    case Superieur = 'superieur';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aucun => 'Aucun',
            self::Coranique => 'Études coraniques',
            self::Elementaire => 'Élémentaire',
            self::Moyen => 'Moyen',
            self::Secondaire => 'Secondaire',
            self::Superieur => 'Supérieur',
        };
    }
}
