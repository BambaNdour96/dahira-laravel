<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Évaluation finale : les objectifs ont-ils été atteints ? */
enum ResultatProjet: string implements HasLabel
{
    case Atteints = 'atteints';
    case Partiels = 'partiels';
    case NonAtteints = 'non_atteints';

    public function getLabel(): string
    {
        return match ($this) {
            self::Atteints => 'Objectifs atteints',
            self::Partiels => 'Objectifs partiellement atteints',
            self::NonAtteints => 'Objectifs non atteints',
        };
    }
}
