<?php

namespace App\Policies;

use App\Models\Cellule;
use App\Models\User;

/**
 * Réservé à l'administrateur (matrice des permissions, cellule 3).
 */
class CellulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function view(User $user, Cellule $objet): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    public function update(User $user, Cellule $objet): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }

    /** Rien ne se supprime : une cellule vide s'archive (voir archiver). */
    public function delete(User $user, Cellule $objet): bool
    {
        return false;
    }

    public function archiver(User $user, Cellule $objet): bool
    {
        return $user->hasAnyRole(['secretaire_general', 'super_administrateur']);
    }
}
