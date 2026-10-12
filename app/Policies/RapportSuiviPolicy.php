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
    public const REDACTION = ['super_administrateur', 'jewrine_national', 'secretaire_general', 'suivi_evaluation'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(ProjetPolicy::LECTURE);
    }

    public function view(User $user, RapportSuivi $rapport): bool
    {
        if (! $user->hasAnyRole(ProjetPolicy::LECTURE)) {
            return false;
        }

        return $rapport->estPublie() || $rapport->auteur_id === $user->id || $user->hasAnyRole(['super_administrateur', 'suivi_evaluation']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(self::REDACTION);
    }

    public function update(User $user, RapportSuivi $rapport): bool
    {
        return ! $rapport->estPublie()
            && ($rapport->auteur_id === $user->id || $user->hasRole('super_administrateur'));
    }

    public function delete(User $user, RapportSuivi $rapport): bool
    {
        return $user->hasRole('super_administrateur')
            || (! $rapport->estPublie() && $rapport->auteur_id === $user->id);
    }

    public function publier(User $user, RapportSuivi $rapport): bool
    {
        return $this->update($user, $rapport);
    }
}
