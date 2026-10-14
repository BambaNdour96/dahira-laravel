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
        return $user->can('referentiel.gerer');
    }

    public function view(User $user, Poste $poste): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function create(User $user): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function update(User $user, Poste $poste): bool
    {
        return $user->can('referentiel.gerer');
    }

    /** Rien ne se supprime : on le désactive. */
    public function delete(User $user, Poste $poste): bool
    {
        return false;
    }
}
