<?php

namespace App\Policies;

use App\Models\RapportSuivi;
use App\Models\User;

/**
 * Rapports rédigés : la commission Suivi et Évaluation et la direction les écrivent ;
 * l'auteur les modifie tant qu'ils sont en brouillon ; le bureau lit les rapports publiés.
 */
class RapportSuiviPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projets.voir');
    }

    public function view(User $user, RapportSuivi $rapport): bool
    {
        if (! $user->can('projets.voir')) {
            return false;
        }

        return $rapport->estPublie() || $rapport->auteur_id === $user->id || $user->can('rapports.tout_lire');
    }

    public function create(User $user): bool
    {
        return $user->can('rapports.rediger');
    }

    public function update(User $user, RapportSuivi $rapport): bool
    {
        return ! $rapport->estPublie()
            && ($rapport->auteur_id === $user->id || $user->can('rapports.corriger'));
    }

    public function delete(User $user, RapportSuivi $rapport): bool
    {
        // Seul un brouillon, jamais diffusé, peut être supprimé par son auteur ; un rapport publié reste.
        return ! $rapport->estPublie() && $rapport->auteur_id === $user->id;
    }

    public function publier(User $user, RapportSuivi $rapport): bool
    {
        return $this->update($user, $rapport);
    }
}
