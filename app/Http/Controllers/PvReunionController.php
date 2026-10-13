<?php

namespace App\Http\Controllers;

use App\Models\Reunion;
use App\Models\User;
use App\Services\ComiteService;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/** Le PV d'une réunion en PDF : lisible par les membres du comité et le Comité directeur. */
class PvReunionController extends Controller
{
    public function __invoke(Request $request, Reunion $reunion, ComiteService $comites): Response
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && ComiteService::peutLire($utilisateur, $reunion->instance), 403);
        abort_unless($reunion->estPubliee() || ComiteService::peutRediger($utilisateur, $reunion), 403);

        $reunion->load(['instance.president', 'secretaire', 'points', 'decisions', 'actions.responsable', 'actions.commission', 'presences.membre']);
        $logo = public_path('images/logo-ndk.png');

        $pdf = new Dompdf(['defaultFont' => 'DejaVu Sans']);
        $pdf->loadHtml(view('pdf.pv-reunion', [
            'reunion' => $reunion,
            'quorum' => $comites->quorum($reunion),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo)) : null,
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return response((string) $pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="PV-'.Str::slug($reunion->instance->code.'-'.$reunion->debut->format('Y-m-d')).'.pdf"',
        ]);
    }
}
