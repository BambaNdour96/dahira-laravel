<?php

namespace App\Http\Controllers;

use App\Models\Collecte;
use App\Models\User;
use App\Policies\CollectePolicy;
use App\Services\CollecteService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Le rapport complet d'un programme en PDF : collecte, jour J, cellules et détail des engagements. */
class ProgrammeRapportController extends Controller
{
    public function __invoke(Request $request, Collecte $collecte, CollecteService $service): Response
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && $utilisateur->hasAnyRole([...CollectePolicy::BUREAU, 'suivi_evaluation']), 403);

        $collecte->load('evenement');
        $evenement = $collecte->evenement;
        $logo = public_path('images/logo-ndk.png');

        $html = view('pdf.rapport-programme', [
            'collecte' => $collecte,
            'chiffres' => $service->statistiques($collecte),
            'cellules' => $service->parCellule($collecte),
            'engagements' => $collecte->engagements()->with(['membre.cellule', 'collecte'])->get()
                ->sortBy(fn ($engagement): string => $engagement->membre->cellule->nom.' '.$engagement->membre->nom),
            'jourJ' => $evenement === null ? null : [
                'evenement' => $evenement,
                'presents' => $evenement->presences()->count(),
                'concernes' => $evenement->membresConcernes(),
            ],
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->render();

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response((string) $pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Rapport-'.Str::slug($collecte->titre).'.pdf"',
        ]);
    }
}
