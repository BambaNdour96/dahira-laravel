<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La date de naissance se choisit en trois listes (jour, mois, année), plus simples sur un téléphone
 * qu'un calendrier. Elles sont réunies ici en une date unique, contrôlée ensuite comme avant.
 */
class AssemblerDateNaissance
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('date_naissance_annee') && ! $request->filled('date_naissance')) {
            $jour = (int) $request->input('date_naissance_jour');
            $mois = (int) $request->input('date_naissance_mois');
            $annee = (int) $request->input('date_naissance_annee');

            $request->merge([
                'date_naissance' => $jour > 0 && $mois > 0 && $annee > 0
                    ? (checkdate($mois, $jour, $annee) ? sprintf('%04d-%02d-%02d', $annee, $mois, $jour) : 'date-invalide')
                    : null,
            ]);
        }

        return $next($request);
    }
}
