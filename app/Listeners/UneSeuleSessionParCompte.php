<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/**
 * Une nouvelle connexion ferme automatiquement les autres sessions du même compte.
 * Exception : « Voir en tant que » ne déconnecte pas la personne consultée.
 */
class UneSeuleSessionParCompte
{
    public function handle(Login $event): void
    {
        if (config('session.driver') !== 'database' || ! $event->user instanceof User) {
            return;
        }

        if (request()->routeIs('en-tant-que', 'en-tant-que.retour')) {
            return;
        }

        DB::table('sessions')
            ->where('user_id', $event->user->id)
            ->where('id', '!=', session()->getId())
            ->delete();
    }
}
