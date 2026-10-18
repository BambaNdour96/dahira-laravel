<?php

namespace App\Http\Controllers;

use App\Models\Cellule;
use App\Models\ChangementCellule;
use App\Models\Membre;
use App\Models\User;
use App\Services\ChangementCelluleService;
use App\Support\MessagesValidation;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** « Ma cellule » : la cellule du membre, son historique, et sa demande de changement. */
class CelluleController extends Controller
{
    public function index(Request $request): View
    {
        $membre = self::membre($request);

        return view('espace.cellule', [
            'membre' => $membre->load(['cellule', 'historiqueCellules.cellule']),
            'demandes' => $membre->changementsCellule()->with(['depart', 'arrivee'])->latest()->limit(5)->get(),
            'enAttente' => $membre->changementsCellule()->where('statut', ChangementCellule::EN_ATTENTE)->exists(),
            'cellules' => Cellule::query()->enActivite()->whereKeyNot($membre->cellule_id)->orderBy('nom')->pluck('nom', 'id'),
        ]);
    }

    public function demander(Request $request, ChangementCelluleService $changements): RedirectResponse
    {
        $membre = self::membre($request);
        $donnees = $request->validate([
            'cellule_id' => ['required', 'integer', Rule::exists('cellules', 'id')->whereNull('archive_le')],
            'motif' => ['nullable', 'string', 'max:255'],
        ], MessagesValidation::tous());

        try {
            $changements->demander($membre, Cellule::query()->whereKey($donnees['cellule_id'])->firstOrFail(), isset($donnees['motif']) ? (string) $donnees['motif'] : null, self::utilisateur($request));
        } catch (DomainException $e) {
            return back()->withErrors(['cellule_id' => $e->getMessage()]);
        }

        return redirect()->route('cellule.index')->with('succes', 'Votre demande est envoyée au super administrateur, qui la validera.');
    }

    public function retirer(Request $request, ChangementCellule $changement, ChangementCelluleService $changements): RedirectResponse
    {
        try {
            $changements->retirer($changement, self::utilisateur($request));
        } catch (DomainException $e) {
            return back()->withErrors(['cellule_id' => $e->getMessage()]);
        }

        return redirect()->route('cellule.index')->with('succes', 'Votre demande est retirée.');
    }

    private static function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }

    private static function membre(Request $request): Membre
    {
        $membre = self::utilisateur($request)->membre;
        abort_if($membre === null, 404);

        return $membre;
    }
}
