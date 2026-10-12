<?php

namespace App\Http\Controllers;

use App\Models\Projet;
use Dompdf\Dompdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Rapport d'un projet en PDF : situation, étapes, indicateurs, journal et évaluation. */
class RapportProjetController extends Controller
{
    public function __invoke(Projet $projet): Response
    {
        Gate::authorize('view', $projet);

        $logo = public_path('images/logo-ndk.png');

        $html = view('pdf.rapport-projet', [
            'projet' => $projet->load(['commission', 'responsable', 'collecte']),
            'diagnostic' => $projet->diagnostic(),
            'jalons' => $projet->jalons()->orderBy('echeance')->get(),
            'indicateurs' => $projet->indicateurs()->get(),
            'journal' => $projet->suivis()->with('auteur')->latest()->limit(30)->get(),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->render();

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response((string) $pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Rapport-projet-'.Str::slug($projet->titre).'.pdf"',
        ]);
    }
}
