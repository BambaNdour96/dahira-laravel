<?php

namespace App\Policies;

use App\Models\Exercice;
use App\Models\User;

/**
 * Les dates se fixent par l'administrateur (référentiel) ; la Finance et le Jewrine national voient les exercices pour
 * leur clôture et leur bilan. Un exercice clos ne se modifie plus.
 */
class ExercicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('referentiel.gerer') || $user->can('finances.voir') || $user->can('exercices.preparer_cloture') || $user->can('exercices.cloturer');
    }

    public function view(User $user, Exercice $objet): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function update(User $user, Exercice $objet): bool
    {
        return $user->can('referentiel.gerer') && ! $objet->estClos();
    }

    /** Rien ne se supprime : un exercice reste dans l'historique. */
    public function delete(User $user, Exercice $objet): bool
    {
        return false;
    }
}
