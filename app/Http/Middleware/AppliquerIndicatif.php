<?php

namespace App\Http\Middleware;

use App\Support\Indicatifs;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si le formulaire envoie un indicatif, on l'ajoute au numéro avant tout traitement.
 */
class AppliquerIndicatif
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('indicatif') && is_string($request->input('telephone'))) {
            $request->merge([
                'telephone' => Indicatifs::combiner((string) $request->input('indicatif'), (string) $request->input('telephone')),
            ]);
        }

        return $next($request);
    }
}
