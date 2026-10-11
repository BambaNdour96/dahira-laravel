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
    public function index(Request $request, CollecteService $service): View
    {
        $membre = $this->membre($request);

        $fiches = $service->ouvertesPour($membre)->map(function (Collecte $collecte) use ($service, $membre): array {
            $engagement = $service->engagementDe($collecte, $membre);
            $reste = $engagement?->reste() ?? 0;

            return [
                'collecte' => $collecte,
                'engagement' => $engagement,
                'peutFixer' => $service->peutFixer($collecte, $membre),
                'minimum' => $service->minimum($collecte),
                'verse' => $engagement?->verse() ?? 0,
                'reste' => $reste,
                'enCours' => $service->versementEnCours($collecte, $membre),
                'lienWave' => LienWave::collecte($reste),
                'versements' => $collecte->paiements()->where('membre_id', $membre->id)->latest('declare_le')->get(),
                'jauge' => $collecte->jaugeVisibleParLesMembres() ? $collecte->taux() : null,
            ];
        });

        return view('espace.engagements', ['membre' => $membre, 'fiches' => $fiches]);
    }

    public function fixer(Request $request, Collecte $collecte, CollecteService $service): RedirectResponse
    {
        $membre = $this->membre($request);

        $request->validate([
            'montant' => ['required', 'integer', 'min:1', 'max:100000000'],
        ], [
            'montant.required' => 'Indiquez le montant de votre engagement.',
            'montant.min' => 'Le montant doit être positif.',
        ]);

        try {
            $service->fixerParMembre($collecte, $membre, (int) $request->input('montant'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['montant' => $e->getMessage()]);
        }

        return redirect()->route('engagements.index')
            ->with('succes', 'Votre engagement pour « '.$collecte->titre.' » est enregistré. Qu\'Allah l\'accepte.');
    }

    public function declarer(Request $request, Collecte $collecte, CollecteService $service): RedirectResponse
    {
        $membre = $this->membre($request);

        $request->validate([
            'montant' => ['required', 'integer', 'min:1', 'max:100000000'],
        ], [
            'montant.required' => 'Indiquez le montant versé.',
        ]);

        [$reference, $numeroWave] = PaiementMembreController::valider($request);

        try {
            $service->declarer($collecte, $membre, (int) $request->input('montant'), $reference, $numeroWave);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['reference' => $e->getMessage()]);
        }

        return redirect()->route('engagements.index')
            ->with('succes', 'Merci ! Votre versement est en cours de vérification par le Jewrine finance.');
    }

    private function membre(Request $request): Membre
    {
        $user = $request->user();
        $membre = $user instanceof User ? $user->membre : null;

        abort_unless($membre !== null && $membre->statut === StatutMembre::Valide, 404);

        return $membre;
    }
}
