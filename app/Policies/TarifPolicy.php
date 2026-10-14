<?php

namespace App\Policies;

use App\Models\Tarif;
use App\Models\User;

/**
 * Réservé à l'administrateur (matrice des permissions, cellule 3).
 */
class TarifPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function view(User $user, Tarif $objet): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function create(User $user): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function update(User $user, Tarif $objet): bool
    {
        return $user->can('referentiel.gerer');
    }

    /** Rien ne se supprime : on le désactive. */
    public function delete(User $user, Tarif $objet): bool
    {
        return false;
    }
}
