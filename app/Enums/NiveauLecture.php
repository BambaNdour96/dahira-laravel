<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** La lecture, ou l'écriture, de l'arabe. */
enum NiveauLecture: string implements HasLabel
{
    case NeSaitPas = 'ne_sait_pas';
    case Difficulte = 'difficulte';
    case Couramment = 'couramment';
    case Excellent = 'excellent';

    public function getLabel(): string
    {
        return match ($this) {
            self::NeSaitPas => 'Ne sait pas',
            self::Difficulte => 'Avec difficulté',
            self::Couramment => 'Couramment',
            self::Excellent => 'Excellent',
        };
    }

    /** « Ne sait pas lire », « Lit avec difficulté »… ou la même chose pour l'écriture. */
    public function libelle(bool $ecriture = false): string
    {
        return match ($this) {
            self::NeSaitPas => $ecriture ? 'Ne sait pas écrire' : 'Ne sait pas lire',
            self::Difficulte => $ecriture ? 'Écrit avec difficulté' : 'Lit avec difficulté',
            self::Couramment => $ecriture ? 'Écrit couramment' : 'Lit couramment',
            self::Excellent => 'Excellent',
        };
    }
}
