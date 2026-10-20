<?php

namespace App\Http\Controllers;

use App\Models\Exercice;
use App\Models\User;
use App\Services\FinanceRapportService;
use App\Support\Confidentiel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Le bilan d'un exercice en PDF, et le rapport d'une période en Excel (avec le détail par membre pour la Finance). */
class RapportFinancierController extends Controller
{
    public function __construct(private readonly FinanceRapportService $rapports) {}

    private function autoriser(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && ($utilisateur->can('finances.voir') || $utilisateur->can('exercices.preparer_cloture') || $utilisateur->can('exercices.cloturer')), 403);

        return $utilisateur;
    }

    public function bilan(Request $request, Exercice $exercice): Response
    {
        $utilisateur = $this->autoriser($request);
        Confidentiel::noter($utilisateur, 'Bilan de l\'exercice '.$exercice->libelle.' (PDF)', $exercice->estClos() ? 'Bilan figé' : 'Bilan provisoire');

        return response($this->rapports->pdfBilan($exercice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Bilan-'.Str::slug($exercice->libelle).'.pdf"',
        ]);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $utilisateur = $this->autoriser($request);
        $valeurs = $request->validate(['debut' => ['required', 'date'], 'fin' => ['required', 'date', 'after_or_equal:debut']]);
        $debut = Carbon::parse($valeurs['debut']);
        $fin = Carbon::parse($valeurs['fin']);
        $avecMembres = $request->boolean('membres') && $utilisateur->can('finances.detail_membres');

        Confidentiel::noter($utilisateur, 'Rapport financier (Excel) du '.$debut->format('d/m/Y').' au '.$fin->format('d/m/Y'), $avecMembres ? 'Avec le détail par membre' : null);

        $chemin = tempnam(sys_get_temp_dir(), 'rapport').'.xlsx';
        $this->rapports->excel($this->rapports->rapport($debut, $fin), $avecMembres ? $this->rapports->detailParMembre($debut, $fin) : null, $chemin);

        return response()->download($chemin, 'Rapport-financier-'.$debut->toDateString().'-'.$fin->toDateString().'.xlsx')->deleteFileAfterSend();
    }
}
