<?php

namespace App\Listeners;

use App\Support\BlocageCompte;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class VerifierBlocageCompte
{
    public function handle(Attempting $event): void
    {
        $cle = BlocageCompte::cle($event->credentials);

        if ($cle === null || ! RateLimiter::tooManyAttempts($cle, BlocageCompte::ESSAIS)) {
            return;
        }

        $minutes = (int) ceil(RateLimiter::availableIn($cle) / 60);
        $message = 'Trop de mots de passe faux pour ce compte. Réessayez dans '.$minutes
            .' minute(s), ou contactez votre Jewrine de cellule.';

        throw ValidationException::withMessages(['telephone' => $message, 'data.telephone' => $message]);
    }
}
