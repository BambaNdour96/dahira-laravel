<?php

namespace App\Http\Controllers;

use App\Models\LotCartes;
use App\Models\User;
use App\Services\CartePhysiqueService;
use App\Support\Confidentiel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Les fichiers d'un lot pour l'imprimeur : le PDF des cartes, ou la liste CSV. */
class LotCartesController extends Controller
{
    public function __invoke(Request $request, LotCartes $lot, string $format, CartePhysiqueService $service): Response
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && $utilisateur->can('cartes.imprimer'), 403);
        abort_unless(in_array($format, ['pdf', 'csv'], true), 404);

        Confidentiel::noter($utilisateur, 'Lot de cartes '.$lot->numero.' ('.strtoupper($format).')', $lot->cartes()->count().' carte(s)');

        return $format === 'pdf'
            ? response($service->pdf($lot), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$lot->numero.'.pdf"'])
            : response($service->csv($lot), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$lot->numero.'.csv"']);
    }
}
