<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\BlocageCompte;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\RateLimiter;

class CompterEchecConnexion
{
    public function handle(Failed $event): void
    {
        $cle = BlocageCompte::cle($event->credentials);

        if ($cle === null) {
            return;
        }

        RateLimiter::hit($cle, BlocageCompte::DUREE_SECONDES);

        if (RateLimiter::attempts($cle) !== BlocageCompte::ESSAIS) {
            return;
        }

        $superAdmins = User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'super_administrateur'))->get();

        if ($superAdmins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Compte bloqué après '.BlocageCompte::ESSAIS.' mots de passe faux')
            ->body('Numéro concerné : '.str_replace('blocage-compte:', '', $cle)
                .'. Le blocage dure 15 minutes. Détails dans l\'historique des connexions.')
            ->danger()
            ->sendToDatabase($superAdmins);
    }
}
