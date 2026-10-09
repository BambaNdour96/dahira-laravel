<?php

namespace App\Http\Middleware;

use App\Listeners\MemoriserNumero;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sur la connexion, si le membre utilise le numéro mémorisé, on le reprend du cookie chiffré :
 * le numéro complet ne passe jamais par la page.
 */
class UtiliserNumeroMemorise
{
    public function handle(Request $request, Closure $next): Response
    {
        $numero = $request->cookie(MemoriserNumero::COOKIE);

        if ($request->isMethod('post') && $request->is('connexion')
            && $request->boolean('numero_memorise') && is_string($numero) && $numero !== '') {
            $request->merge(['telephone' => $numero]);
        }

        return $next($request);
    }
}
