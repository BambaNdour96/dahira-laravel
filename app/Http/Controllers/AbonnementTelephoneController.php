<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Activer ou désactiver les notifications sur un téléphone (abonnement Web Push du navigateur). */
class AbonnementTelephoneController extends Controller
{
    public function store(Request $request): Response
    {
        $donnees = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $this->utilisateur($request)->updatePushSubscription(
            $donnees['endpoint'],
            $donnees['keys']['p256dh'],
            $donnees['keys']['auth'],
            'aes128gcm',
        );

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $request->validate(['endpoint' => ['required', 'string', 'max:500']]);

        $this->utilisateur($request)->deletePushSubscription((string) $request->input('endpoint'));

        return response()->noContent();
    }

    private function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }
}
