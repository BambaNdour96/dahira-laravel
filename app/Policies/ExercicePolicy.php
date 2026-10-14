<?php

namespace App\Policies;

use App\Models\Exercice;
use App\Models\User;

/**
 * Réservé à l'administrateur (matrice des permissions, cellule 3).
 */
class ExercicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function view(User $user, Exercice $objet): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function create(User $user): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function update(User $user, Exercice $objet): bool
    {
        return $user->can('referentiel.gerer');
    }

    /** Rien ne se supprime : un exercice reste dans l'historique. */
    public function delete(User $user, Exercice $objet): bool
    {
        return false;
    }
}
