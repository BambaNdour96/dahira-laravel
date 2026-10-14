<?php

namespace App\Models\Concerns;

use DomainException;

/**
 * « Rien ne se perd » : une donnée importante ne se supprime jamais, même par erreur dans un formulaire.
 * On l'annule, on l'archive, on y met fin ou on la corrige par une opération nouvelle et tracée.
 * La base refuse aussi l'effacement (clés étrangères sans cascade).
 */
trait Indelebile
{
    public static function bootIndelebile(): void
    {
        static::deleting(function (): never {
            throw new DomainException('Cette donnée ne se supprime pas : on l\'annule, on l\'archive ou on la corrige par une opération tracée.');
        });
    }
}
