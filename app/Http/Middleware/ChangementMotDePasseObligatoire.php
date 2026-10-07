<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Après un mot de passe provisoire, la personne doit choisir le sien avant d'aller plus loin.
 */
class ChangementMotDePasseObligatoire
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if (! $utilisateur instanceof User || ! (bool) $utilisateur->getAttribute('doit_changer_mot_de_passe')) {
            return $next($request);
        }

        if ($request->routeIs('mot-de-passe.*', 'logout', 'filament.admin.auth.logout') || $request->is('livewire*')) {
            return $next($request);
        }

        return redirect()->route('mot-de-passe.edit');
    }
}
