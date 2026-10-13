<?php

namespace App\Http\Controllers;

use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Presence;
use App\Models\User;
use App\Services\PointageService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Le pointage sur le téléphone : la liste des membres à pointer, présents, excusés,
 * et l'ajout à la main de ceux qui ont oublié leur carte.
 */
class PointageController extends Controller
{
    public function index(Request $request, PointageService $pointage): View|RedirectResponse
    {
        $rendezVous = $pointage->rendezVousDuMoment($this->utilisateur($request));

        if ($rendezVous->count() === 1) {
            return redirect()->route('pointage.show', $rendezVous->first());
        }

        return view('espace.pointage-index', ['rendezVous' => $rendezVous]);
    }

    public function show(Request $request, Evenement $evenement, PointageService $pointage): View
    {
        $utilisateur = $this->utilisateur($request);
        abort_unless($pointage->peutPointer($utilisateur, $evenement), 403);

        $presences = Presence::query()->where('evenement_id', $evenement->id)->get()->keyBy('membre_id');

        return view('espace.pointage', [
            'evenement' => $evenement->load('cellule'),
            'membres' => $pointage->membresAPointer($evenement, $utilisateur),
            'presences' => $presences,
            'bilan' => $pointage->bilan($evenement),
            'perimetre' => $pointage->perimetre($utilisateur, $evenement),
        ]);
    }

    public function pointer(Request $request, Evenement $evenement, Membre $membre, PointageService $pointage): RedirectResponse
    {
        $utilisateur = $this->utilisateur($request);
        $donnees = $request->validate([
            'action' => ['required', 'in:present,excuse,retirer'],
            'motif' => ['nullable', 'string', 'max:150'],
        ]);

        try {
            if ($donnees['action'] === 'retirer') {
                $pointage->retirer($evenement, $membre, $utilisateur);
                $message = $membre->prenom.' '.$membre->nom.' : pointage annulé.';
            } else {
                $presence = $pointage->pointer($evenement, $membre, $utilisateur, 'manuel', $donnees['action'], $donnees['motif'] ?? null);
                $message = $membre->prenom.' '.$membre->nom.' : '.($presence->statut === 'excuse' ? 'absent excusé.' : 'présent ✓'.($presence->mode === 'secours' ? ' (pointée en secours)' : ''));
            }
        } catch (DomainException $e) {
            return redirect()->to(route('pointage.show', $evenement).'#membre-'.$membre->id)->with('pointage_erreur', $e->getMessage());
        }

        return redirect()->to(route('pointage.show', $evenement).'#membre-'.$membre->id)->with('pointage_succes', $message);
    }

    private function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }
}
