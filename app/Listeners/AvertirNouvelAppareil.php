<?php

namespace App\Listeners;

use App\Models\Connexion;
use App\Models\SessionActive;
use App\Models\User;
use App\Notifications\NotificationMembre;
use Illuminate\Auth\Events\Login;

/**
 * Prévient la personne quand son compte est ouvert depuis un appareil jamais utilisé auparavant.
 */
class AvertirNouvelAppareil
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User || request()->routeIs('en-tant-que', 'en-tant-que.retour')) {
            return;
        }

        $navigateur = mb_substr((string) request()->userAgent(), 0, 255);

        $precedentes = Connexion::query()
            ->where('user_id', $event->user->id)
            ->where('reussie', true)
            ->where('created_at', '<', now()->subSeconds(30));

        // Première connexion, ou appareil déjà connu : rien à signaler.
        if (! (clone $precedentes)->exists() || (clone $precedentes)->where('navigateur', $navigateur)->exists()) {
            return;
        }

        $appareil = (new SessionActive)->forceFill(['user_agent' => $navigateur])->appareil();

        $event->user->notify(new NotificationMembre(
            'Nouvelle connexion à votre compte',
            'Votre compte a été ouvert le '.now()->format('d/m/Y à H:i').' depuis un nouvel appareil ('.$appareil.'). Si ce n\'est pas vous, changez votre mot de passe.',
            route('mot-de-passe.edit'),
            'Changer mon mot de passe',
        ));
    }
}
