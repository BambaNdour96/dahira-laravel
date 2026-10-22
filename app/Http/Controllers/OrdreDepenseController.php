<?php

namespace App\Http\Controllers;

use App\Models\CommissionComite;
use App\Models\OrdreDepense;
use App\Models\User;
use App\Services\CollecteService;
use App\Services\OrdreDepenseService;
use App\Support\MessagesValidation;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/** Le chef d'une commission d'événement demande un ordre de dépense (paiement, avance, remboursement) et justifie ses avances, depuis son téléphone. */
class OrdreDepenseController extends Controller
{
    public function pourCommission(Request $request, CommissionComite $commission, OrdreDepenseService $ordres): RedirectResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        $donnees = $request->validate([
            'objet' => ['required', 'string', 'max:255'],
            'libelle_depense_id' => ['required', 'integer'],
            'montant' => ['required', 'integer', 'min:1'],
            'nature' => ['nullable', 'in:'.implode(',', array_keys(OrdreDepense::NATURES))],
            'beneficiaire' => ['nullable', 'string', 'max:150'],
            'devis' => ['nullable', 'file', 'max:5120'],
        ], MessagesValidation::tous());

        try {
            $devis = $request->file('devis');
            $ordre = $ordres->demander([...$donnees, 'nature' => (string) ($donnees['nature'] ?? OrdreDepense::PAIEMENT), 'beneficiaire' => (string) ($donnees['beneficiaire'] ?? ''), 'rattachement' => 'programme', 'commission_comite_id' => $commission->id],
                $devis instanceof UploadedFile ? $devis : null, $utilisateur);
        } catch (DomainException $e) {
            return back()->withInput()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', 'Ordre '.$ordre->numero.' envoyé pour signature : vous serez prévenu quand il sera payé.');
    }

    /** Le chef justifie son avance : un achat, son montant, la photo du reçu. */
    public function justifier(Request $request, OrdreDepense $ordre, OrdreDepenseService $ordres): RedirectResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        $donnees = $request->validate([
            'objet' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'integer', 'min:1'],
            'piece' => ['required', 'file', 'max:5120'],
        ], MessagesValidation::tous());

        try {
            $piece = $request->file('piece');
            abort_unless($piece instanceof UploadedFile, 422);
            $ordres->justifier($ordre, (string) $donnees['objet'], (int) $donnees['montant'], $piece, $utilisateur);
        } catch (DomainException $e) {
            return back()->withInput()->with('comite_erreur', $e->getMessage());
        }

        $ordre->refresh();

        return back()->with('comite_succes', $ordre->soldee_le !== null
            ? 'Avance '.$ordre->numero.' entièrement justifiée : elle est soldée.'
            : 'Pièce ajoutée. Reste à justifier ou à rendre : '.CollecteService::fcfa($ordre->resteAJustifier()).'.');
    }
}
