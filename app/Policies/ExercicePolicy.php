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
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function view(User $user, Exercice $objet): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function update(User $user, Exercice $objet): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function delete(User $user, Exercice $objet): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }
}
