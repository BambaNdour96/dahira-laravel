<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Models\Collecte;
use App\Models\Membre;
use App\Models\User;
use App\Services\CollecteService;
use App\Support\LienWave;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * « Mes engagements » : pour chaque collecte ouverte, l'engagement du membre, ce qu'il a versé et ce qui reste.
 */
class EngagementController extends Controller
{
    /** Ses engagements, ou ceux d'un proche dont il gère la fiche (« Ma famille »). */
    public function index(Request $request, CollecteService $service, ?Membre $proche = null): View
    {
        $membre = $this->membre($request, $proche);

        $fiches = $service->ouvertesPour($membre)->map(function (Collecte $collecte) use ($service, $membre): array {
            $engagement = $service->engagementDe($collecte, $membre);
            $reste = $engagement?->reste() ?? 0;
            $enVerification = $service->enVerification($collecte, $membre);
            $disponible = max(0, $reste - $enVerification);

            return [
                'collecte' => $collecte,
                'engagement' => $engagement,
                'peutFixer' => $service->peutFixer($collecte, $membre),
                'minimum' => $service->minimum($collecte),
                'verse' => $engagement?->verse() ?? 0,
                'reste' => $reste,
                'enVerification' => $enVerification,
                'disponible' => $disponible,
                'lienWave' => LienWave::collecte($disponible),
                'versements' => $collecte->paiements()->where('membre_id', $membre->id)->latest('declare_le')->get(),
                'jauge' => $collecte->jaugeVisibleParLesMembres() ? $collecte->taux() : null,
                'jourJ' => $collecte->evenement,
                'present' => $collecte->evenement !== null
                    && $collecte->evenement->presences()->where('membre_id', $membre->id)->exists(),
            ];
        });

        return view('espace.engagements', ['membre' => $membre, 'proche' => $proche, 'fiches' => $fiches]);
    }

    public function fixer(Request $request, Collecte $collecte, CollecteService $service, ?Membre $proche = null): RedirectResponse
    {
        $membre = $this->membre($request, $proche);

        $request->validate([
            'montant' => ['required', 'integer', 'min:1', 'max:100000000'],
        ], [
            'montant.required' => 'Indiquez le montant de votre Jàyante.',
            'montant.min' => 'Le montant doit être positif.',
        ]);

        try {
            $service->fixerParMembre($collecte, $membre, (int) $request->input('montant'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['montant' => $e->getMessage()]);
        }

        return redirect()->to($this->retour($proche))
            ->with('succes', ($proche !== null ? 'Le Jàyante de '.$proche->prenom : 'Votre Jàyante').' pour « '.$collecte->titre.' » est enregistré. Qu\'Allah l\'accepte.');
    }

    public function declarer(Request $request, Collecte $collecte, CollecteService $service, ?Membre $proche = null): RedirectResponse
    {
        $membre = $this->membre($request, $proche);

        $request->validate([
            'montant' => ['required', 'integer', 'min:1', 'max:100000000'],
        ], [
            'montant.required' => 'Indiquez le montant versé.',
        ]);

        [$reference, $numeroWave, $moyen] = PaiementMembreController::valider($request);

        try {
            $declarant = $request->user();
            $service->declarer($collecte, $membre, (int) $request->input('montant'), $reference, $numeroWave, $declarant instanceof User ? $declarant : null, $moyen);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['montant' => $e->getMessage()]);
        }

        return redirect()->to($this->retour($proche))
            ->with('succes', 'Merci ! Votre versement de '.CollecteService::fcfa((int) $request->input('montant')).' est en cours de vérification par le Jewrine finance. Votre jauge avancera dès sa confirmation.');
    }

    /** Le parent fixe le Jàyante d'un proche dont il gère la fiche (« Ma famille »). */
    public function fixerPourProche(Request $request, Membre $proche, Collecte $collecte, CollecteService $service): RedirectResponse
    {
        return $this->fixer($request, $collecte, $service, $proche);
    }

    /** Le parent déclare un versement pour un proche dont il gère la fiche. */
    public function declarerPourProche(Request $request, Membre $proche, Collecte $collecte, CollecteService $service): RedirectResponse
    {
        return $this->declarer($request, $collecte, $service, $proche);
    }

    private function retour(?Membre $proche): string
    {
        return $proche !== null ? route('famille.engagements', $proche) : route('engagements.index');
    }

    private function membre(Request $request, ?Membre $proche): Membre
    {
        $user = $request->user();

        if ($proche !== null && $user instanceof User) {
            FamilleController::verifier($user, $proche);
        }

        $membre = $proche ?? ($user instanceof User ? $user->membre : null);

        abort_unless($membre !== null && $membre->statut === StatutMembre::Valide, 404);

        return $membre;
    }
}
