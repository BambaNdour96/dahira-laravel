<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Models\Evenement;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EvenementController extends Controller
{
    /** Les prochains événements du membre, et ceux auxquels il a participé. */
    public function index(Request $request): View
    {
        $utilisateur = $request->user();
        $membre = $utilisateur instanceof User ? $utilisateur->membre : null;

        abort_unless($membre !== null && $membre->statut === StatutMembre::Valide, 404);

        return view('espace.evenements', [
            'aVenir' => Evenement::query()
                ->where(fn ($cible) => $cible->whereNull('cellule_id')->orWhere('cellule_id', $membre->cellule_id))
                ->where('debut', '>=', now()->startOfDay())
                ->orderBy('debut')
                ->get(),
            'participations' => Presence::query()
                ->where('membre_id', $membre->id)
                ->with('evenement')
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }
}
