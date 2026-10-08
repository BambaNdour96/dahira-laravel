<?php

namespace App\Policies;

use App\Models\Annonce;
use App\Models\User;

/**
 * Seul le super administrateur publie, modifie et retire les annonces.
 */
class AnnoncePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function view(User $user, Annonce $annonce): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function update(User $user, Annonce $annonce): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function delete(User $user, Annonce $annonce): bool
    {
        return $user->hasRole('super_administrateur');
    }
}
