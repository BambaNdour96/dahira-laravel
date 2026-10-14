<?php

namespace App\Policies;

use App\Models\Projet;
use App\Models\User;

/**
 * Projets : la commission Suivi et Évaluation et la direction les créent et les suivent ;
 * le Jewrine finance les consulte ; seul le super administrateur supprime.
 */
class ProjetPolicy
{
    public const LECTURE = ['super_administrateur', 'jewrine_national', 'secretaire_general', 'tresorier', 'suivi_evaluation'];

    public const GESTION = ['super_administrateur', 'jewrine_national', 'secretaire_general', 'suivi_evaluation'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::LECTURE);
    }

    public function view(User $user, Projet $projet): bool
    {
        return $user->hasAnyRole(self::LECTURE);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::GESTION);
    }

    public function update(User $user, Projet $projet): bool
    {
        return $user->hasAnyRole(self::GESTION);
    }

    /** Rien ne se supprime : un projet arrêté passe à l'état « Abandonné ». */
    public function delete(User $user, Projet $projet): bool
    {
        return false;
    }
}
