<?php

namespace App\Policies;

use App\Models\Paiement;
use App\Models\User;
use App\Support\AntiFraude;
use DomainException;

/**
 * Le Jewrine finance gère les paiements des membres. La direction les consulte,
 * et valide seulement les paiements du bureau qui lui sont confiés (validation croisée).
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
        return $user->hasAnyRole(['tresorier', 'super_administrateur']);
    }

    /** Confirmer ou refuser une déclaration. */
    public function update(User $user, Paiement $paiement): bool
    {
        if ($user->hasAnyRole(['tresorier', 'super_administrateur'])) {
            return true;
        }

        if (! $paiement->exists || ! $user->hasRole('administrateur')) {
            return false;
        }

        try {
            AntiFraude::verifierValidateur($paiement->membre, $user);
        } catch (DomainException) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Paiement $paiement): bool
    {
        return false;
    }
}
