<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SituationProfessionnelle: string implements HasLabel
{
    case EleveEtudiant = 'eleve_etudiant';
    case Salarie = 'salarie';
    case Independant = 'independant';
    case Entrepreneur = 'entrepreneur';
    case SansEmploi = 'sans_emploi';
    case Retraite = 'retraite';

    public function getLabel(): string
    {
        return match ($this) {
            self::EleveEtudiant => 'Élève ou étudiant',
            self::Salarie => 'Salarié',
            self::Independant => 'Indépendant ou commerçant',
            self::Entrepreneur => 'Entrepreneur / Chef d\'entreprise',
            self::SansEmploi => 'Sans emploi',
            self::Retraite => 'Retraité',
        };
    }
}
