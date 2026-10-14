<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Avant d'accéder au panneau, chaque membre du bureau lit et accepte la charte de confidentialité. */
class ExigerCharteConfidentialite
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if ($utilisateur instanceof User
            && $utilisateur->charte_acceptee_le === null
            && $utilisateur->can('espace.responsable')) {
            return redirect()->guest(route('charte'));
        }

        return $next($request);
    }
}
