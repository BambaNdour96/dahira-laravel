<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Models\Collecte;
use App\Models\Membre;
use App\Models\Parametre;
use App\Models\Renouvellement;
use App\Models\User;
use App\Services\CartePhysiqueService;
use App\Services\CollecteService;
use App\Services\CotisationService;
use App\Services\PaiementPourAutrui;
use App\Services\PaiementService;
use App\Services\RenouvellementService;
use App\Support\LienWave;
use App\Support\MessagesValidation;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * « Payer pour un membre » : la cotisation ou le Jàyante d'un autre membre qui a son propre compte, retrouvé par son
 * matricule ou son téléphone. On ne voit que son nom, sa cellule et les collectes où il est engagé, jamais ses montants.
 */
class PaiementPourAutruiController extends Controller
{
    public function show(Request $request, PaiementPourAutrui $autrui, CotisationService $cotisations, CollecteService $collectes, RenouvellementService $renouvellements, CartePhysiqueService $plastique, PaiementService $paiements): View
    {
        $payeur = self::payeur($request);
        $recherche = trim((string) $request->query('recherche', ''));
        $beneficiaire = null;
        $erreur = null;

        if ($recherche !== '') {
            try {
                $beneficiaire = $autrui->trouver($recherche, $payeur);
            } catch (DomainException $e) {
                $erreur = $e->getMessage();
            }
        }

        $mensuel = $cotisations->montantMensuel();

        return view('espace.payer-pour-un-membre', [
            'recherche' => $recherche,
            'erreur' => $erreur,
            'beneficiaire' => $beneficiaire,
            'mensuel' => $mensuel,
            'choix' => CotisationService::CHOIX_MOIS,
            'liensWave' => collect(CotisationService::CHOIX_MOIS)->mapWithKeys(fn (int $nombre): array => [$nombre => LienWave::cotisation($nombre, $mensuel)])->filter()->all(),
            'lienGeneral' => Parametre::valeur('wave_lien_paiement'),
            'renouvellement' => $beneficiaire !== null && in_array(($renouvellement = $renouvellements->pour($beneficiaire))?->statut, Renouvellement::A_PAYER, true) ? $renouvellement : null,
            'fraisRenouvellement' => $paiements->montantFraisCarte(),
            'tarifPlastique' => $plastique->tarif(),
            'plastiqueCommandable' => $beneficiaire !== null && $beneficiaire->statut === StatutMembre::Valide
                && $plastique->commandeEnCours($beneficiaire) === null && $plastique->paiementEnCours($beneficiaire) === null,
            'collectes' => $beneficiaire !== null
                ? $collectes->ouvertesPour($beneficiaire)->filter(fn (Collecte $collecte): bool => $collectes->engagementDe($collecte, $beneficiaire) !== null)->values()
                : collect(),
        ]);
    }

    public function cotisation(Request $request, Membre $beneficiaire, PaiementPourAutrui $autrui, CotisationService $cotisations): RedirectResponse
    {
        $payeur = self::payeur($request);
        $request->validate(['nombre_mois' => ['required', 'integer', Rule::in(CotisationService::CHOIX_MOIS)]], MessagesValidation::tous());
        [$reference, $numeroWave, $moyen] = PaiementMembreController::valider($request);

        try {
            $autrui->verifier($beneficiaire, $payeur);
            $cotisations->declarer($beneficiaire, $reference, (int) $request->input('nombre_mois'), $numeroWave, $payeur, $moyen);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['reference' => $e->getMessage()]);
        }

        return redirect()->route('autrui.show')
            ->with('succes', 'Merci ! Votre paiement pour '.$beneficiaire->prenom.' est en cours de vérification. '.$beneficiaire->prenom.' est prévenu et recevra son reçu.');
    }

    public function versement(Request $request, Membre $beneficiaire, Collecte $collecte, PaiementPourAutrui $autrui, CollecteService $collectes): RedirectResponse
    {
        $payeur = self::payeur($request);
        $request->validate(['montant' => ['required', 'integer', 'min:1', 'max:100000000']], MessagesValidation::tous());
        [$reference, $numeroWave, $moyen] = PaiementMembreController::valider($request);

        try {
            $autrui->verifier($beneficiaire, $payeur);
            $collectes->declarer($collecte, $beneficiaire, (int) $request->input('montant'), $reference, $numeroWave, $payeur, $moyen);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['montant' => $e->getMessage()]);
        }

        return redirect()->route('autrui.show')
            ->with('succes', 'Merci ! Votre versement pour '.$beneficiaire->prenom.' est en cours de vérification. '.$beneficiaire->prenom.' est prévenu et recevra son reçu.');
    }

    public function renouvellement(Request $request, Membre $beneficiaire, RenouvellementService $renouvellements): RedirectResponse
    {
        $payeur = self::payeur($request);
        [$reference, $numeroWave, $moyen] = PaiementMembreController::valider($request);
        $renouvellement = $renouvellements->pour($beneficiaire);

        try {
            abort_unless($renouvellement !== null, 404);
            $renouvellements->declarerPourAutrui($renouvellement, $reference, $numeroWave, $payeur, $moyen);
        } catch (DomainException $e) {
            return back()->withErrors(['renouvellement' => $e->getMessage()]);
        }

        return redirect()->route('autrui.show')
            ->with('succes', 'Merci ! Votre paiement du renouvellement de '.$beneficiaire->prenom.' est en cours de vérification. '.$beneficiaire->prenom.' est prévenu et recevra son reçu.');
    }

    public function plastique(Request $request, Membre $beneficiaire, CartePhysiqueService $plastique): RedirectResponse
    {
        $payeur = self::payeur($request);
        [$reference, $numeroWave, $moyen] = PaiementMembreController::valider($request);

        try {
            $plastique->commanderPourAutrui($beneficiaire, $reference, $numeroWave, $payeur, $moyen);
        } catch (DomainException $e) {
            return back()->withErrors(['plastique' => $e->getMessage()]);
        }

        return redirect()->route('autrui.show')
            ->with('succes', 'Merci ! La carte en plastique de '.$beneficiaire->prenom.' est commandée : le paiement est en cours de vérification.');
    }

    private static function payeur(Request $request): User
    {
        $payeur = $request->user();
        abort_unless($payeur instanceof User && $payeur->membre !== null, 403);

        return $payeur;
    }
}
