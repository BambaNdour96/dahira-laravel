<?php

namespace App\Http\Controllers;

use App\Models\ParticipationSympathisant;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Le reçu d'une participation de sympathisant, à imprimer ou à lui envoyer. */
class RecuSympathisantController extends Controller
{
    public function __invoke(Request $request, ParticipationSympathisant $participation): Response
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && $utilisateur->can('sympathisants.participations'), 403);

        $participation->load(['collecte', 'sympathisant', 'auteur']);
        $logo = public_path('images/logo-ndk.png');

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml(view('pdf.recu-sympathisant', [
            'participation' => $participation,
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->render(), 'UTF-8');
        $pdf->setPaper('A5', 'landscape');
        $pdf->render();

        return response((string) $pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Recu-'.$participation->numero_recu.'.pdf"',
        ]);
    }
}
