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
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function view(User $user, Commission $commission): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function update(User $user, Commission $commission): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function delete(User $user, Commission $commission): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }
}
