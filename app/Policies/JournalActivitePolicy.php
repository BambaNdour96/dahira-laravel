<?php

namespace App\Policies;

use App\Models\JournalActivite;
use App\Models\User;

/**
 * Le journal se consulte, il ne se modifie jamais.
 */
class JournalActivitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('administrateur');
    }

    public function view(User $user, JournalActivite $ligne): bool
    {
        return $user->hasRole('administrateur');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, JournalActivite $ligne): bool
    {
        return false;
    }

    public function delete(User $user, JournalActivite $ligne): bool
    {
        return false;
    }
}
