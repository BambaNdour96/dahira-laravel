<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ConnexionEnTantQue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnTantQueController extends Controller
{
    public function demarrer(Request $request, User $user, ConnexionEnTantQue $service): RedirectResponse
    {
        $auteur = $request->user();

        abort_unless($auteur instanceof User && $auteur->hasRole('super_administrateur'), 403);
        abort_if($user->hasRole('super_administrateur') || $user->is($auteur), 403);

        $service->demarrer($auteur, $user);

        $estDuBureau = $user->hasAnyRole(['administrateur', 'tresorier', 'responsable_section']);

        return redirect($estDuBureau ? '/admin' : route('espace'));
    }

    public function arreter(ConnexionEnTantQue $service): RedirectResponse
    {
        $original = $service->arreter();

        return redirect($original !== null ? '/admin/comptes-bureau' : route('accueil'));
    }
}
