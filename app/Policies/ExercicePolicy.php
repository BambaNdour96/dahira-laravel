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
        return $user->hasRole('administrateur');
    }

    public function view(User $user, Exercice $objet): bool
    {
        return $user->hasRole('administrateur');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrateur');
    }

    public function update(User $user, Exercice $objet): bool
    {
        return $user->hasRole('administrateur');
    }

    public function delete(User $user, Exercice $objet): bool
    {
        return $user->hasRole('administrateur');
    }
}
