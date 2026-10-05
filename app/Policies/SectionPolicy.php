<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

/**
 * Réservé à l'administrateur (matrice des permissions, section 3).
 */
class SectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('administrateur');
    }

    public function view(User $user, Section $objet): bool
    {
        return $user->hasRole('administrateur');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('administrateur');
    }

    public function update(User $user, Section $objet): bool
    {
        return $user->hasRole('administrateur');
    }

    public function delete(User $user, Section $objet): bool
    {
        return $user->hasRole('administrateur');
    }
}
