<?php

namespace App\Policies;

use App\Models\Parametre;
use App\Models\User;

/**
 * Les réglages se modifient, mais ne se créent ni ne se suppriment depuis le panneau.
 */
class ParametrePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('plateforme.administrer');
    }

    public function view(User $user, Parametre $parametre): bool
    {
        return $user->can('plateforme.administrer');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Parametre $parametre): bool
    {
        return $user->can('plateforme.administrer');
    }

    public function delete(User $user, Parametre $parametre): bool
    {
        return false;
    }
}
