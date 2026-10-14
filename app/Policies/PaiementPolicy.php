<?php

namespace App\Policies;

use App\Models\Paiement;
use App\Models\User;
use App\Support\AntiFraude;
use DomainException;

/**
 * Le Jewrine finance gère les paiements des membres (« paiements.valider »). La direction les consulte,
 * et valide seulement les paiements des responsables qui lui sont confiés (« paiements.valider_bureau »,
 * validation croisée). Le super administrateur valide en secours (« paiements.secours »).
 */
class PaiementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('paiements.voir');
    }

    public function view(User $user, Paiement $paiement): bool
    {
        return $user->can('paiements.voir');
    }

    /** Enregistrer un paiement en espèces. */
    public function create(User $user): bool
    {
        return $user->can('paiements.encaisser');
    }

    /** Confirmer ou refuser une déclaration. */
    public function update(User $user, Paiement $paiement): bool
    {
        if ($user->can('paiements.valider') || $user->can('paiements.secours')) {
            return true;
        }

        if (! $paiement->exists || ! $user->can('paiements.valider_bureau')) {
            return false;
        }

        try {
            AntiFraude::verifierValidateur($paiement->membre, $user);
        } catch (DomainException) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Paiement $paiement): bool
    {
        return false;
    }
}
