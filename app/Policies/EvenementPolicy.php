<?php

namespace App\Policies;

use App\Models\Evenement;
use App\Models\User;

/**
 * Le Jewrine national, le Secrétaire général et le super administrateur gèrent les événements ;
 * la commission Suivi et Évaluation les consulte.
 */
class EvenementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['jewrine_national', 'secretaire_general', 'super_administrateur', 'suivi_evaluation']);
    }

    public function view(User $user, Evenement $evenement): bool
    {
        return $user->hasAnyRole(['jewrine_national', 'secretaire_general', 'super_administrateur', 'suivi_evaluation']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['jewrine_national', 'secretaire_general', 'super_administrateur']);
    }

    public function update(User $user, Evenement $evenement): bool
    {
        return $user->hasAnyRole(['jewrine_national', 'secretaire_general', 'super_administrateur']);
    }

    /** Rien ne se supprime : un événement s'annule (voir annuler). */
    public function delete(User $user, Evenement $evenement): bool
    {
        return false;
    }

    public function annuler(User $user, Evenement $evenement): bool
    {
        return $user->hasAnyRole(['jewrine_national', 'secretaire_general', 'super_administrateur']) && ! $evenement->estAnnule();
    }
}
