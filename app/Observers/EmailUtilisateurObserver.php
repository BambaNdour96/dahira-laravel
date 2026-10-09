<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\ConfirmerEmail;

/**
 * Les adresses sont enregistrées en minuscules. Une nouvelle adresse, ou une adresse
 * modifiée, doit être confirmée avant de servir aux envois importants.
 */
class EmailUtilisateurObserver
{
    public function saving(User $user): void
    {
        if (is_string($user->email)) {
            $user->email = mb_strtolower(trim($user->email)) ?: null;
        }
    }

    public function created(User $user): void
    {
        if (filled($user->email) && $user->email_verified_at === null) {
            $user->notify(new ConfirmerEmail);
        }
    }

    public function updated(User $user): void
    {
        if (! $user->wasChanged('email')) {
            return;
        }

        if ($user->email_verified_at !== null && ! $user->wasChanged('email_verified_at')) {
            $user->forceFill(['email_verified_at' => null])->saveQuietly();
        }

        if (filled($user->email)) {
            $user->notify(new ConfirmerEmail);
        }
    }
}
