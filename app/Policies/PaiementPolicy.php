<?php

namespace App\Policies;

use App\Models\Paiement;
use App\Models\User;

/**
 * Les paiements sont gérés par le Jewrine finance et l'administrateur.
 */
class PaiementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['administrateur', 'tresorier']);
    }

    public function view(User $user, Paiement $paiement): bool
    {
        return $user->hasAnyRole(['administrateur', 'tresorier']);
    }

    /** Enregistrer un paiement en espèces. */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['administrateur', 'tresorier']);
    }

    /** Confirmer ou refuser une déclaration. */
    public function update(User $user, Paiement $paiement): bool
    {
        return $user->hasAnyRole(['administrateur', 'tresorier']);
    }

    public function delete(User $user, Paiement $paiement): bool
    {
        return false;
    }
}
