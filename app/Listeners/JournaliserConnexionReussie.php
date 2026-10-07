<?php

namespace App\Listeners;

use App\Models\Connexion;
use App\Models\User;
use App\Services\ConnexionEnTantQue;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Str;

class JournaliserConnexionReussie
{
    public function handle(Login $event): void
    {
        // Un « voir en tant que » n'est pas une vraie connexion : il est noté au journal d'activité.
        if (session()->has(ConnexionEnTantQue::CLE)) {
            return;
        }

        $user = $event->user;

        Connexion::query()->create([
            'user_id' => $user instanceof User ? $user->id : null,
            'telephone' => $user instanceof User ? $user->telephone : null,
            'reussie' => true,
            'ip' => request()->ip(),
            'navigateur' => Str::limit((string) request()->userAgent(), 250, ''),
        ]);
    }
}
