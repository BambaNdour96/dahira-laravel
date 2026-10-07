<?php

namespace App\Http\Controllers;

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RecuController extends Controller
{
    /** Le membre télécharge ses reçus ; le bureau peut télécharger tous les reçus. */
    public function show(Request $request, Paiement $paiement): Response
    {
        $utilisateur = $request->user();
        $proprietaire = $utilisateur instanceof User && $paiement->membre->user_id === $utilisateur->id;

        abort_unless($proprietaire || Gate::allows('view', $paiement), 403);
        abort_if($paiement->statut !== StatutPaiement::Confirme || $paiement->numero_recu === null, 404);

        $logo = public_path('images/logo-ndk.png');

        $html = view('pdf.recu', [
            'paiement' => $paiement->load(['membre.cellule', 'traitePar', 'cotisations']),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->render();

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A5', 'portrait');
        $pdf->render();

        return response((string) $pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$paiement->nomFichierRecu().'"',
        ]);
    }
}
