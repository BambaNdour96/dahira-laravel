<?php

namespace App\Policies;

use App\Models\Connexion;
use App\Models\User;

class ConnexionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('plateforme.administrer');
    }

    public function view(User $user, Connexion $connexion): bool
    {
        return $user->can('plateforme.administrer');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Connexion $connexion): bool
    {
        return false;
    }

    public function delete(User $user, Connexion $connexion): bool
    {
        return false;
    }
}
