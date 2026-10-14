<?php

namespace App\Policies;

use App\Models\Projet;
use App\Models\User;

/**
 * Projets : capacités « projets.voir » et « projets.gerer » (voir App\Support\Capacites).
 */
class ProjetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projets.voir');
    }

    public function view(User $user, Projet $projet): bool
    {
        return $user->can('projets.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('projets.gerer');
    }

    public function update(User $user, Projet $projet): bool
    {
        return $user->can('projets.gerer');
    }

    /** Rien ne se supprime : un projet arrêté passe à l'état « Abandonné ». */
    public function delete(User $user, Projet $projet): bool
    {
        return false;
    }
}
