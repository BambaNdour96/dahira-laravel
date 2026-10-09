<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

/**
 * Note l'heure de connexion, pour fermer la session d'un membre au bout de 10 minutes.
 */
class NoterDebutSession
{
    public function handle(Login $event): void
    {
        if (request()->hasSession()) {
            request()->session()->put('connecte_le', now()->getTimestamp());
        }
    }
}
