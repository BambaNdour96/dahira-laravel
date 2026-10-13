<?php

namespace App\Http\Controllers;

use App\Models\RemiseFonds;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Le bordereau de remise des espèces d'un représentant au Jewrine finance national. */
class BordereauRemiseController extends Controller
{
    public function __invoke(Request $request, RemiseFonds $remise): Response
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User
            && ($utilisateur->hasAnyRole(['tresorier', 'super_administrateur']) || $utilisateur->id === $remise->representant_id), 403);

        $remise->load(['cellule', 'representant', 'receveur', 'paiements.membre', 'paiements.collecte']);
        $logo = public_path('images/logo-ndk.png');

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml(view('pdf.bordereau-remise', [
            'remise' => $remise,
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response((string) $pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Bordereau-'.$remise->numero.'.pdf"',
        ]);
    }
}
