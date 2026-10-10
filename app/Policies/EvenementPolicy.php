<?php

namespace App\Policies;

use App\Models\Evenement;
use App\Models\User;

/**
 * Le Jewrine national, le Secrétaire général et le super administrateur gèrent les événements.
 */
class EvenementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['jewrine_national', 'secretaire_general', 'super_administrateur']);
    }

    public function view(User $user, Evenement $evenement): bool
    {
        return $user->hasAnyRole(['jewrine_national', 'secretaire_general', 'super_administrateur']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['jewrine_national', 'secretaire_general', 'super_administrateur']);
    }

    public function update(User $user, Evenement $evenement): bool
    {
        return $user->hasAnyRole(['jewrine_national', 'secretaire_general', 'super_administrateur']);
    }

    public function delete(User $user, Evenement $evenement): bool
    {
        return $user->hasAnyRole(['jewrine_national', 'secretaire_general', 'super_administrateur']);
    }
}
