<?php

namespace App\Http\Controllers;

use App\Enums\Sexe;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Presence;
use App\Models\Sympathisant;
use App\Models\User;
use App\Models\VisiteSympathisant;
use App\Services\PointageService;
use App\Services\SympathisantService;
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

        $perimetre = $pointage->perimetre($utilisateur, $evenement);

        return view('espace.pointage', [
            'evenement' => $evenement->load('cellule'),
            'membres' => $pointage->membresAPointer($evenement, $utilisateur),
            'presences' => $presences,
            'bilan' => $pointage->bilan($evenement),
            'perimetre' => $perimetre,
            'sympathisants' => Sympathisant::query()
                ->where('cellule_id', $evenement->cellule_id)
                ->whereNull('membre_id')
                ->when($perimetre === 'hommes', fn ($query) => $query->where('sexe', Sexe::Homme->value))
                ->when($perimetre === 'femmes', fn ($query) => $query->where('sexe', Sexe::Femme->value))
                ->orderBy('nom')
                ->get(),
            'venus' => VisiteSympathisant::query()->where('evenement_id', $evenement->id)->pluck('sympathisant_id')->all(),
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

    public function accueillirSympathisant(Request $request, Evenement $evenement, SympathisantService $sympathisants): RedirectResponse
    {
        $donnees = $request->validate([
            'prenom' => ['required', 'string', 'max:80'],
            'nom' => ['required', 'string', 'max:80'],
            'sexe' => ['required', 'in:H,F'],
            'telephone' => ['nullable', 'string', 'max:20'],
            'accord' => ['accepted'],
        ], ['accord.accepted' => 'Demandez-lui son accord pour être recensé, puis cochez la case.']);

        try {
            $sympathisant = $sympathisants->accueillir($evenement, [
                'prenom' => (string) $donnees['prenom'],
                'nom' => (string) $donnees['nom'],
                'sexe' => (string) $donnees['sexe'],
                'telephone' => isset($donnees['telephone']) ? (string) $donnees['telephone'] : null,
                'accord' => true,
            ], $this->utilisateur($request));
        } catch (DomainException $e) {
            return redirect()->to(route('pointage.show', $evenement).'#sympathisants')->with('pointage_erreur', $e->getMessage());
        }

        return redirect()->to(route('pointage.show', $evenement).'#sympathisants')
            ->with('pointage_succes', 'Bienvenue à '.$sympathisant->nomComplet().' : sympathisant enregistré et présent ✓');
    }

    public function sympathisantPresent(Request $request, Evenement $evenement, Sympathisant $sympathisant, SympathisantService $sympathisants): RedirectResponse
    {
        try {
            $sympathisants->noterVisite($evenement, $sympathisant, $this->utilisateur($request));
        } catch (DomainException $e) {
            return redirect()->to(route('pointage.show', $evenement).'#sympathisants')->with('pointage_erreur', $e->getMessage());
        }

        return redirect()->to(route('pointage.show', $evenement).'#sympathisants')
            ->with('pointage_succes', $sympathisant->nomComplet().' : présent ✓');
    }

    public function visiteur(Request $request, Evenement $evenement, SympathisantService $sympathisants): RedirectResponse
    {
        $ecart = $request->input('ecart') === '-1' ? -1 : 1;

        try {
            $total = $sympathisants->compterVisiteur($evenement, $ecart, $this->utilisateur($request));
        } catch (DomainException $e) {
            return redirect()->to(route('pointage.show', $evenement).'#sympathisants')->with('pointage_erreur', $e->getMessage());
        }

        return redirect()->to(route('pointage.show', $evenement).'#sympathisants')
            ->with('pointage_succes', $total.' visiteur(s) anonyme(s).');
    }

    private function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }
}
