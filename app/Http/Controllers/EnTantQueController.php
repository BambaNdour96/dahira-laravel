<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CasquettesService;
use App\Services\ConnexionEnTantQue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnTantQueController extends Controller
{
    public function demarrer(Request $request, User $user, ConnexionEnTantQue $service): RedirectResponse
    {
        $auteur = $request->user();

        abort_unless($auteur instanceof User && $auteur->can('plateforme.administrer'), 403);
        abort_if(CasquettesService::estSuperAdministrateur($user) || $user->is($auteur), 403);

        $service->demarrer($auteur, $user);

        $estDuBureau = $user->can('panneau.accueil');

        return redirect($estDuBureau ? '/admin' : route('espace'));
    }

    public function arreter(ConnexionEnTantQue $service): RedirectResponse
    {
        $original = $service->arreter();

        return redirect($original !== null ? '/admin/comptes' : route('accueil'));
    }
}
