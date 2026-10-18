<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Ce qu'est le proche pour le membre qui gère sa fiche (son responsable). */
enum LienFamille: string implements HasLabel
{
    case Enfant = 'enfant';
    case Pupille = 'pupille';
    case Conjoint = 'conjoint';
    case Parent = 'parent';
    case FrereSoeur = 'frere_soeur';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Enfant => 'Son enfant',
            self::Pupille => 'Enfant dont il ou elle est le tuteur',
            self::Conjoint => 'Son époux ou épouse',
            self::Parent => 'Son père ou sa mère',
            self::FrereSoeur => 'Son frère ou sa sœur',
            self::Autre => 'Un autre proche',
        };
    }
}
