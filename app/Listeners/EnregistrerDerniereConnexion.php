<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

class EnregistrerDerniereConnexion
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->forceFill(['derniere_connexion_at' => now()])->saveQuietly();
        }
    }
}
