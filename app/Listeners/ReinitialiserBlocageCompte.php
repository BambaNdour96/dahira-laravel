<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\RateLimiter;

class ReinitialiserBlocageCompte
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            RateLimiter::clear('blocage-compte:'.$event->user->telephone);
        }
    }
}
