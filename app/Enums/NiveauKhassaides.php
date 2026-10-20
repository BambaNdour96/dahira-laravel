<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** La récitation des khassaides. */
enum NiveauKhassaides: string implements HasLabel
{
    case Aucun = 'aucun';
    case Quelques = 'quelques';
    case Kourel = 'kourel';

    public function getLabel(): string
    {
        return match ($this) {
            self::Aucun => 'Ne récite pas',
            self::Quelques => 'Récite quelques khassaides',
            self::Kourel => 'Récite bien et peut animer un kourel',
        };
    }
}
