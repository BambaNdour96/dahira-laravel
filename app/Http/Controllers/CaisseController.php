<?php

namespace App\Http\Controllers;

use App\Enums\StatutCollecte;
use App\Models\Engagement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Renouvellement;
use App\Models\RepresentantFinance;
use App\Models\User;
use App\Services\CartePhysiqueService;
use App\Services\CollecteService;
use App\Services\CotisationService;
use App\Services\PaiementService;
use App\Services\RenouvellementService;
use App\Services\RepresentantFinanceService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * La caisse du représentant du Jewrine finance, sur son téléphone :
 * valider les paiements Wave, encaisser les espèces (cotisations, Sass, Jàyante), suivre sa caisse.
 */
class CaisseController extends Controller
{
    public function index(Request $request, RepresentantFinanceService $service): View
    {
        $utilisateur = $this->representant($request);

        return view('espace.caisse', [
            'representations' => $service->representationsDe($utilisateur),
            'aValider' => $service->aValider($utilisateur),
            'operations' => $service->operationsEnCaisse($utilisateur),
            'enCaisse' => $service->enCaisse($utilisateur),
            'membres' => $service->membresCouverts($utilisateur),
        ]);
    }

    public function membre(Request $request, Membre $membre, CotisationService $cotisations, CollecteService $collectes, RenouvellementService $renouvellements, CartePhysiqueService $plastique, PaiementService $paiements): View
    {
        $utilisateur = $this->representant($request);
        abort_unless(RepresentantFinance::represente($utilisateur, $membre), 403);

        $engagements = Engagement::query()
            ->with('collecte')
            ->where('membre_id', $membre->id)
            ->whereHas('collecte', fn ($query) => $query->where('statut', StatutCollecte::Ouverte->value))
            ->get()
            ->filter(fn (Engagement $engagement): bool => $engagement->reste() > 0);

        return view('espace.caisse-membre', [
            'membre' => $membre,
            'moisEnRetard' => count($cotisations->moisEnRetard($membre)),
            'mensuel' => $cotisations->montantMensuel(),
            'engagements' => $engagements,
            'service' => $collectes,
            'renouvellement' => in_array(($renouvellement = $renouvellements->pour($membre))?->statut, Renouvellement::A_PAYER, true) ? $renouvellement : null,
            'fraisRenouvellement' => $paiements->montantFraisCarte(),
            'tarifPlastique' => $plastique->tarif(),
            'plastiqueCommandee' => $plastique->commandeEnCours($membre) !== null || $plastique->paiementEnCours($membre) !== null,
        ]);
    }

    public function cotisation(Request $request, Membre $membre, CotisationService $cotisations): RedirectResponse
    {
        $utilisateur = $this->representant($request);
        abort_unless(RepresentantFinance::represente($utilisateur, $membre), 403);
        $nombre = (int) $request->validate(['nombre_mois' => ['required', 'integer', 'min:1', 'max:24']])['nombre_mois'];

        try {
            $paiement = $cotisations->enregistrerEspeces($membre, $nombre, $utilisateur);
        } catch (DomainException $e) {
            return back()->with('caisse_erreur', $e->getMessage());
        }

        return redirect()->route('caisse.index')
            ->with('caisse_succes', 'Cotisation de '.$membre->prenom.' '.$membre->nom.' encaissée : '.CollecteService::fcfa($paiement->montant).'. Le reçu est parti.');
    }

    /** Les frais de renouvellement, reçus en espèces. */
    public function renouvellement(Request $request, Membre $membre, RenouvellementService $renouvellements, PaiementService $paiements): RedirectResponse
    {
        $utilisateur = $this->representant($request);
        abort_unless(RepresentantFinance::represente($utilisateur, $membre), 403);

        try {
            $renouvellements->enregistrerEspeces($membre, $utilisateur);
        } catch (DomainException $e) {
            return back()->with('caisse_erreur', $e->getMessage());
        }

        return redirect()->route('caisse.index')
            ->with('caisse_succes', 'Renouvellement de '.$membre->prenom.' '.$membre->nom.' encaissé : '.CollecteService::fcfa($paiements->montantFraisCarte()).'. Le reçu est parti.');
    }

    /** La carte en plastique, payée en espèces : elle attend le prochain lot. */
    public function plastique(Request $request, Membre $membre, CartePhysiqueService $plastique): RedirectResponse
    {
        $utilisateur = $this->representant($request);
        abort_unless(RepresentantFinance::represente($utilisateur, $membre), 403);

        try {
            $paiement = $plastique->enregistrerEspeces($membre, $utilisateur);
        } catch (DomainException $e) {
            return back()->with('caisse_erreur', $e->getMessage());
        }

        return redirect()->route('caisse.index')
            ->with('caisse_succes', 'Carte en plastique de '.$membre->prenom.' '.$membre->nom.' encaissée : '.CollecteService::fcfa($paiement->montant).'. Elle entrera dans le prochain lot.');
    }

    public function versement(Request $request, Membre $membre, Engagement $engagement, CollecteService $collectes): RedirectResponse
    {
        $utilisateur = $this->representant($request);
        abort_unless(RepresentantFinance::represente($utilisateur, $membre) && $engagement->membre_id === $membre->id, 403);
        $montant = (int) $request->validate(['montant' => ['required', 'integer', 'min:1']])['montant'];

        if ($montant > $engagement->reste()) {
            return back()->with('caisse_erreur', 'Ce montant dépasse ce qui reste à verser : '.CollecteService::fcfa($engagement->reste()).'.');
        }

        try {
            $collectes->enregistrerEspeces($engagement->collecte, $membre, $montant, $utilisateur);
        } catch (DomainException $e) {
            return back()->with('caisse_erreur', $e->getMessage());
        }

        return redirect()->route('caisse.index')
            ->with('caisse_succes', $engagement->collecte->titre.' : '.CollecteService::fcfa($montant).' encaissés pour '.$membre->prenom.' '.$membre->nom.'. Le reçu est parti.');
    }

    public function valider(Request $request, Paiement $paiement, RepresentantFinanceService $service): RedirectResponse
    {
        try {
            $service->valider($this->representant($request), $paiement);
        } catch (DomainException $e) {
            return back()->with('caisse_erreur', $e->getMessage());
        }

        return back()->with('caisse_succes', 'Paiement de '.$paiement->membre->prenom.' '.$paiement->membre->nom.' validé : le reçu est parti.');
    }

    public function refuser(Request $request, Paiement $paiement, RepresentantFinanceService $service): RedirectResponse
    {
        $motif = (string) $request->validate(['motif' => ['required', 'string', 'max:255']])['motif'];

        try {
            $service->refuser($this->representant($request), $paiement, $motif);
        } catch (DomainException $e) {
            return back()->with('caisse_erreur', $e->getMessage());
        }

        return back()->with('caisse_succes', 'Paiement refusé : le membre est prévenu, avec le motif.');
    }

    private function representant(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && $utilisateur->can('caisse.tenir'), 403);

        return $utilisateur;
    }
}
