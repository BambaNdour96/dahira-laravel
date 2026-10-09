<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cookie;

/**
 * Après une connexion réussie, le téléphone garde le numéro (cookie chiffré, un an),
 * pour ne demander que le mot de passe la fois suivante.
 */
class MemoriserNumero
{
    public const COOKIE = 'ndk_numero';

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || request()->routeIs('en-tant-que', 'en-tant-que.retour')) {
            return;
        }

        Cookie::queue(self::COOKIE, $event->user->telephone, 60 * 24 * 365, null, null, null, true, false, 'lax');
    }
}
