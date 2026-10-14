<?php

namespace App\Policies;

use App\Models\Commission;
use App\Models\User;

/**
 * Réservé à l'administrateur (matrice des permissions, section 3).
 */
class CommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function view(User $user, Commission $commission): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function create(User $user): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function update(User $user, Commission $commission): bool
    {
        return $user->can('referentiel.gerer');
    }

    /** Rien ne se supprime : on la désactive. */
    public function delete(User $user, Commission $commission): bool
    {
        return false;
    }
}
