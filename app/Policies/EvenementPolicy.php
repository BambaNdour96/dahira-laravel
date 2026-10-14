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
        return $user->can('evenements.voir');
    }

    public function view(User $user, Evenement $evenement): bool
    {
        return $user->can('evenements.voir');
    }

    public function create(User $user): bool
    {
        return $user->can('evenements.gerer');
    }

    public function update(User $user, Evenement $evenement): bool
    {
        return $user->can('evenements.gerer');
    }

    /** Rien ne se supprime : un événement s'annule (voir annuler). */
    public function delete(User $user, Evenement $evenement): bool
    {
        return false;
    }

    public function annuler(User $user, Evenement $evenement): bool
    {
        return $user->can('evenements.gerer') && ! $evenement->estAnnule();
    }
}
