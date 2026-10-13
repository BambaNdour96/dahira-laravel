<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CompteBureauService;
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
            && $utilisateur->hasAnyRole([...CompteBureauService::ROLES_BUREAU, 'super_administrateur', 'representant_finance', 'comite_directeur'])) {
            return redirect()->guest(route('charte'));
        }

        return $next($request);
    }
}
