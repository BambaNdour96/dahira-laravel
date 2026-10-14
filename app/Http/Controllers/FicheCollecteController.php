<?php

namespace App\Http\Controllers;

use App\Enums\ModeCollecte;
use App\Enums\ProgrammeCollecte;
use App\Models\Cellule;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Fiche de paramétrage d'une collecte, à imprimer : le bureau coche les paramètres décidés,
 * puis le super administrateur crée la collecte à partir de la fiche.
 */
class FicheCollecteController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && $utilisateur->can('programmes.piloter'), 403);

        $logo = public_path('images/logo-ndk.png');

        $html = view('pdf.fiche-collecte', [
            'programmes' => ProgrammeCollecte::cases(),
            'modes' => ModeCollecte::cases(),
            'cellules' => Cellule::query()->enActivite()->orderBy('nom')->pluck('nom')->all(),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->render();

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response((string) $pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Fiche-parametrage-collecte.pdf"',
        ]);
    }
}
