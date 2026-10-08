<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EvenementController extends Controller
{
    /** Les événements auxquels le membre a participé (présence enregistrée au scan de sa carte). */
    public function index(Request $request): View
    {
        $utilisateur = $request->user();
        $membre = $utilisateur instanceof User ? $utilisateur->membre : null;

        abort_unless($membre !== null && $membre->statut === StatutMembre::Valide, 404);

        return view('espace.evenements', [
            'participations' => Presence::query()
                ->where('membre_id', $membre->id)
                ->with('evenement')
                ->latest()
                ->paginate(20),
        ]);
    }
}
