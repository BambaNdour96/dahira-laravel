<?php

namespace App\Policies;

use App\Models\Commission;
use App\Models\User;
use App\Services\CommissionService;

/**
 * Les commissions permanentes : le super administrateur les crée et les paramètre (« referentiel.gerer ») ;
 * le Jewrine d'une commission la consulte et gère ses membres.
 */
class CommissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('referentiel.gerer') || $user->can('commissions.gerer') || Commission::query()->dirigeesPar($user)->exists();
    }

    public function view(User $user, Commission $commission): bool
    {
        return $user->can('referentiel.gerer') || CommissionService::peutDiriger($user, $commission);
    }

    public function create(User $user): bool
    {
        return $user->can('referentiel.gerer');
    }

    public function update(User $user, Commission $commission): bool
    {
        return $user->can('referentiel.gerer');
    }

    /** Rien ne se supprime : on la désactive. */
    public function delete(User $user, Commission $commission): bool
    {
        return false;
    }
}
