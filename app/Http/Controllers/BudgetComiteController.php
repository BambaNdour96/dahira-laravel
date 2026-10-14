<?php

namespace App\Http\Controllers;

use App\Models\CommissionComite;
use App\Models\DepenseCommission;
use App\Models\Instance;
use App\Models\User;
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

        return back()->with('comite_succes', 'Besoin envoyé au président du comité.');
    }

    public function depense(Request $request, CommissionComite $commission, BudgetComiteService $budget): RedirectResponse
    {
        $donnees = $request->validate([
            'objet' => ['required', 'string', 'max:150'],
            'montant' => ['required', 'integer', 'min:1'],
            'date_depense' => ['required', 'date', 'before_or_equal:today'],
            'fournisseur' => ['nullable', 'string', 'max:100'],
            'justificatif' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        try {
            $budget->declarerDepense($commission, [
                'objet' => (string) $donnees['objet'],
                'montant' => (int) $donnees['montant'],
                'date_depense' => (string) $donnees['date_depense'],
                'fournisseur' => isset($donnees['fournisseur']) ? (string) $donnees['fournisseur'] : null,
            ], $request->file('justificatif'), $this->utilisateur($request));
        } catch (DomainException $e) {
            return back()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', 'Dépense déclarée : le Jewrine finance va la valider.');
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

        $instance->load(['president', 'collecte.evenement', 'commissions.chef']);
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
