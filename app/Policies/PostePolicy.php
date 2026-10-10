<?php

namespace App\Policies;

use App\Models\Poste;
use App\Models\User;

/**
 * Réservé à l'administrateur.
 */
class PostePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function view(User $user, Poste $poste): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function update(User $user, Poste $poste): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function delete(User $user, Poste $poste): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }
}
