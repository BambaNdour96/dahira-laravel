<?php

namespace App\Http\Controllers;

use App\Models\CommissionComite;
use App\Models\User;
use App\Services\OrdreDepenseService;
use App\Support\MessagesValidation;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/** Le chef d'une commission d'événement demande un ordre de dépense depuis son téléphone (« Mes comités »). */
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
            'beneficiaire' => ['required', 'string', 'max:150'],
            'devis' => ['nullable', 'file', 'max:5120'],
        ], MessagesValidation::tous());

        try {
            $devis = $request->file('devis');
            $ordre = $ordres->demander([...$donnees, 'rattachement' => 'programme', 'commission_comite_id' => $commission->id], $devis instanceof UploadedFile ? $devis : null, $utilisateur);
        } catch (DomainException $e) {
            return back()->withInput()->with('comite_erreur', $e->getMessage());
        }

        return back()->with('comite_succes', 'Ordre '.$ordre->numero.' envoyé pour signature : vous serez prévenu quand il sera payé.');
    }
}
