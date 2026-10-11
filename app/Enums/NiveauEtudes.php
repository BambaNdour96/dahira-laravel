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
    case Bac = 'bac';
    case Superieur = 'superieur';
    case Licence = 'licence';
    case Master = 'master';
    case BacPlus5 = 'bac_plus_5';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aucun => 'Aucun',
            self::Coranique => 'Études coraniques',
            self::Elementaire => 'Élémentaire',
            self::Moyen => 'Moyen',
            self::Secondaire => 'Secondaire',
            self::Superieur => 'BTS, DUT ou autre bac + 2',
            self::Bac => 'BAC',
            self::Licence => 'Licence',
            self::Master => 'Master',
            self::BacPlus5 => 'BAC + 5 et plus',
            self::Autre => 'Autre (à préciser)',
        };
    }
}
