<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Les programmes de la Daara : les trois grands, puis les autres, créés par le super administrateur. */
enum ProgrammeCollecte: string implements HasLabel
{
    case Magal = 'magal';
    case Ziar = 'ziar';
    case Prokhane = 'prokhane';
    case LaylatoulQadr = 'laylatoul_qadr';
    case Secondaire = 'secondaire';
    case Projet = 'projet';
    case Autre = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Magal => 'Magal de Touba',
            self::Ziar => 'Ziar annuelle',
            self::Prokhane => 'Prokhane',
            self::LaylatoulQadr => 'Laylatoul Qadr',
            self::Secondaire => 'Programme secondaire',
            self::Projet => 'Projet (construction, équipement…)',
            self::Autre => 'Autre collecte',
        };
    }

    public function estGrandProgramme(): bool
    {
        return in_array($this, [self::Magal, self::Ziar, self::Prokhane], true);
    }

    /** Mode proposé par défaut à la création ; le super administrateur peut toujours le changer. */
    public function modeSuggere(): ModeCollecte
    {
        return match ($this) {
            self::Magal => ModeCollecte::EngagementLibre,
            self::Prokhane => ModeCollecte::Minimum,
            self::Ziar => ModeCollecte::Attribue,
            default => ModeCollecte::Identique,
        };
    }
}
