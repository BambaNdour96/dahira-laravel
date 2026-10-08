<?php

namespace App\Http\Controllers;

use App\Models\Carte;
use App\Models\Evenement;
use App\Models\User;
use App\Services\PresenceService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    /** Le contrôleur, après avoir scanné la carte, marque le membre présent. */
    public function store(Request $request, string $jeton, Evenement $evenement, PresenceService $presences): RedirectResponse
    {
        $controleur = $request->user();
        abort_unless($controleur instanceof User, 403);

        $carte = Carte::query()->where('jeton', $jeton)->where('statut', 'active')->with('membre')->first();
        abort_if($carte === null, 404);

        try {
            $nouvelle = $presences->marquer($evenement, $carte->membre, $controleur);
        } catch (DomainException $e) {
            return redirect()->route('verification', $jeton)->with('presence_erreur', $e->getMessage());
        }

        $message = $nouvelle
            ? $carte->membre->prenom.' '.$carte->membre->nom.' est marqué présent : '.$evenement->titre.'.'
            : 'Ce membre était déjà marqué présent à cet événement.';

        return redirect()->route('verification', $jeton)->with('presence_succes', $message);
    }
}
