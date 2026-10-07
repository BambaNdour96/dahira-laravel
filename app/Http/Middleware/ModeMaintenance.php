<?php

namespace App\Http\Middleware;

use App\Models\Parametre;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pendant une maintenance, le site des membres est fermé, sauf pour le super administrateur.
 * La vérification des cartes reste ouverte, pour ne jamais bloquer un événement.
 */
class ModeMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Parametre::valeur('maintenance') !== '1') {
            return $next($request);
        }

        if ($request->is('v/*', 'connexion', 'deconnexion', 'up')) {
            return $next($request);
        }

        $utilisateur = $request->user();

        if ($utilisateur instanceof User && $utilisateur->hasRole('super_administrateur')) {
            return $next($request);
        }

        return response()->view('maintenance', [], 503);
    }
}
