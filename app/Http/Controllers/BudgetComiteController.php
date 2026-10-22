<?php

namespace App\Http\Controllers;

use App\Models\CommissionComite;
use App\Models\DepenseCommission;
use App\Models\Instance;
use App\Models\OrdreDepense;
use App\Models\User;
use App\Services\BilanComiteService;
use App\Services\BudgetComiteService;
use App\Services\ComiteService;
use DomainException;
use Dompdf\Dompdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Les besoins et dépenses déclarés par les chefs de commission, les justificatifs, et le bilan du comité. */
class BudgetComiteController extends Controller
{
    public function besoin(Request $request, CommissionComite $commission, BudgetComiteService $budget): RedirectResponse
    {
        $donnees = $request->validate([
            'designation' => ['required', 'string', 'max:150'],
            'quantite' => ['required', 'integer', 'min:1', 'max:100000'],
            'prix_unitaire' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $budget->proposerBesoin($commission, [
                'designation' => (string) $donnees['designation'],
                'quantite' => (int) $donnees['quantite'],
                'prix_unitaire' => (int) $donnees['prix_unitaire'],
            ], $this->utilisateur($request));
        } catch (DomainException $e) {
            return back()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', 'Besoin envoyé pour arbitrage.');
    }

    /** Le chef rend (ou complète) le bilan de sa commission, depuis son téléphone. */
    public function bilanCommission(Request $request, CommissionComite $commission, BilanComiteService $bilans): RedirectResponse
    {
        $donnees = $request->validate([
            'appreciation' => ['required', 'string'],
            'forces' => ['nullable', 'string', 'max:5000'],
            'faiblesses' => ['nullable', 'string', 'max:5000'],
            'ameliorations' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $bilans->rendreBilanCommission($commission, $donnees, $this->utilisateur($request));
        } catch (DomainException $e) {
            return back()->withInput()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', 'Bilan de la commission '.$commission->nom.' enregistré : le président est prévenu.');
    }

    public function justificatif(Request $request, DepenseCommission $depense): StreamedResponse
    {
        $utilisateur = $this->utilisateur($request);
        abort_unless($depense->justificatif_path !== null, 404);
        abort_unless(ComiteService::peutLire($utilisateur, $depense->commission->instance) || $utilisateur->can('comites.depenses_valider'), 403);

        return Storage::disk('local')->response($depense->justificatif_path);
    }

    public function bilan(Request $request, Instance $instance, ComiteService $comites, BudgetComiteService $budget): Response
    {
        $utilisateur = $this->utilisateur($request);
        abort_unless(ComiteService::peutLire($utilisateur, $instance), 403);

        $instance->load(['president', 'collecte.evenement', 'commissions.chef', 'reunionBilan']);
        $evenement = $instance->collecte?->evenement;
        $logo = public_path('images/logo-ndk.png');

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml(view('pdf.bilan-comite', [
            'instance' => $instance,
            'avancement' => $comites->avancement($instance),
            'budget' => $budget->recapitulatif($instance),
            'depenses' => DepenseCommission::query()->with('commission')
                ->whereIn('commission_id', $instance->commissions()->select('id'))
                ->where('statut', 'validee')->orderBy('date_depense')->get(),
            'ordres' => OrdreDepense::query()->with(['commissionComite', 'commission'])->where('statut', OrdreDepense::EXECUTE)
                ->where(fn ($query) => $query->whereIn('commission_comite_id', $instance->commissions()->select('id'))
                    ->when($instance->collecte_id !== null, fn ($evenement) => $evenement->orWhere('collecte_id', $instance->collecte_id)))
                ->orderBy('execute_le')->get(),
            'bilans' => $instance->bilans()->with(['commission', 'auteur'])->orderByRaw("case type when 'commission' then 1 when 'general' then 2 else 3 end")->orderBy('id')->get(),
            'decisions' => $instance->decisions()->orderBy('numero')->get(),
            'presents' => $evenement?->presents()->count(),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response((string) $pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Bilan-'.Str::slug($instance->nom).'.pdf"',
        ]);
    }

    private function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }
}
