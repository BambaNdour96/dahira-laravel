<?php

namespace App\Policies;

use App\Models\Evenement;
use App\Models\User;

/**
 * Seul le super administrateur crée et gère les événements.
 */
class EvenementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function view(User $user, Evenement $evenement): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function update(User $user, Evenement $evenement): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function delete(User $user, Evenement $evenement): bool
    {
        return $user->hasRole('super_administrateur');
    }
}
