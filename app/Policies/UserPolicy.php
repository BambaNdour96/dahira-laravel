<?php

namespace App\Policies;

use App\Models\User;

/**
 * Seul le super administrateur gère les comptes du bureau.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('plateforme.administrer');
    }

    public function view(User $user, User $compte): bool
    {
        return $user->can('plateforme.administrer');
    }

    public function create(User $user): bool
    {
        return $user->can('plateforme.administrer');
    }

    public function update(User $user, User $compte): bool
    {
        return $user->can('plateforme.administrer');
    }

    public function delete(User $user, User $compte): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
