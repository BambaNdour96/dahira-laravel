<?php

namespace App\Policies;

use App\Models\Collecte;
use App\Models\User;

/**
 * Droits sur les collectes, du super administrateur au Jewrine finance :
 * - le super administrateur crée et paramètre tout ;
 * - le Jewrine national clôture et rouvre ;
 * - la direction (Jewrine national, Secrétaire général) attribue des montants ;
 * - le bureau entier (avec le Jewrine finance) modifie un engagement, avec motif et trace ;
 * - le Jewrine finance confirme les versements (voir PaiementPolicy).
 */
class CollectePolicy
{
    public const BUREAU = ['super_administrateur', 'jewrine_national', 'secretaire_general', 'tresorier'];

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(self::BUREAU);
    }

    public function view(User $user, Collecte $collecte): bool
    {
        return $user->hasAnyRole(self::BUREAU);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function update(User $user, Collecte $collecte): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function delete(User $user, Collecte $collecte): bool
    {
        return $user->hasRole('super_administrateur');
    }

    public function cloturer(User $user, Collecte $collecte): bool
    {
        return $user->hasAnyRole(['super_administrateur', 'jewrine_national']);
    }

    public function attribuer(User $user, Collecte $collecte): bool
    {
        return $user->hasAnyRole(['super_administrateur', 'jewrine_national', 'secretaire_general']);
    }

    public function modifierEngagement(User $user, Collecte $collecte): bool
    {
        return $user->hasAnyRole(self::BUREAU);
    }
}
