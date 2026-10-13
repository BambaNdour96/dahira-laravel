<?php

namespace App\Http\Controllers;

use App\Models\RapportSuivi;
use App\Models\User;
use App\Support\Confidentiel;
use Dompdf\Dompdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Un rapport rédigé en PDF, avec les chiffres de son sujet. */
class RapportSuiviPdfController extends Controller
{
    public function __invoke(RapportSuivi $rapport): Response
    {
        Gate::authorize('view', $rapport);

        $utilisateur = auth()->user();
        abort_unless($utilisateur instanceof User, 403);
        Confidentiel::noter($utilisateur, 'Rapport rédigé (PDF) : '.$rapport->titre);

        $rapport->load(['projet', 'collecte', 'auteur']);
        $logo = public_path('images/logo-ndk.png');

        $html = view('pdf.rapport-suivi', [
            'rapport' => $rapport,
            'confidentiel' => Confidentiel::mention($utilisateur),
            'contenu' => RapportSuivi::propre($rapport->contenu),
            'recommandations' => RapportSuivi::propre($rapport->recommandations),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->render();

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response((string) $pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Rapport-'.Str::slug($rapport->titre).'.pdf"',
        ]);
    }
}
