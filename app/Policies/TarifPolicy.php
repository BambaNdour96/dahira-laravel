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
        return $user->hasRole('administrateur');
    }

    public function view(User $user, Tarif $objet): bool
    {
        return $user->hasRole('administrateur');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrateur');
    }

    public function update(User $user, Tarif $objet): bool
    {
        return $user->hasRole('administrateur');
    }

    public function delete(User $user, Tarif $objet): bool
    {
        return $user->hasRole('administrateur');
    }
}
