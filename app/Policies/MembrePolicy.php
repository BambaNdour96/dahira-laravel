<?php

namespace App\Policies;

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\User;

class MembrePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrateur', 'tresorier', 'responsable_section']);
    }

    public function view(User $user, Membre $membre): bool
    {
        return $user->hasAnyRole(['administrateur', 'tresorier'])
            || $this->estResponsableDe($user, $membre);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrateur', 'responsable_section']);
    }

    public function update(User $user, Membre $membre): bool
    {
        return $user->hasRole('administrateur')
            || $this->estResponsableDe($user, $membre);
    }

    public function delete(User $user, Membre $membre): bool
    {
        return $user->hasRole('administrateur')
            && $membre->statut === StatutMembre::Rejete;
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('administrateur');
    }

    /** Un membre voit sa propre photo ; le bureau voit celles qu'il a le droit de consulter. */
    public function voirPhoto(User $user, Membre $membre): bool
    {
        return $membre->user_id === $user->id || $this->view($user, $membre);
    }

    /** Seul l'administrateur révoque une carte (perte, vol). */
    public function revoquerCarte(User $user, Membre $membre): bool
    {
        return $user->hasRole('administrateur');
    }

    /**
     * L'administrateur peut tout faire. Le Jewrine de cellule traite seulement
     * les inscriptions en attente de sa propre cellule.
     */
    public function changerStatut(User $user, Membre $membre, StatutMembre $cible): bool
    {
        if ($user->hasRole('administrateur')) {
            return true;
        }

        return $this->estResponsableDe($user, $membre)
            && $membre->statut === StatutMembre::EnAttente
            && in_array($cible, [StatutMembre::Valide, StatutMembre::ACorriger, StatutMembre::Rejete], true);
    }

    private function estResponsableDe(User $user, Membre $membre): bool
    {
        return $user->hasRole('responsable_section')
            && $membre->cellule()->where('responsable_id', $user->id)->exists();
    }
}
