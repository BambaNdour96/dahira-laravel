<?php

namespace App\Http\Controllers;

use App\Models\Collecte;
use App\Models\Engagement;
use App\Models\User;
use App\Policies\CollectePolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Les engagements d'un programme, en fichier ouvrable dans Excel (séparateur « ; », accents conservés). */
class ProgrammeExportController extends Controller
{
    public function __invoke(Request $request, Collecte $collecte): StreamedResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && $utilisateur->hasAnyRole([...CollectePolicy::BUREAU, 'suivi_evaluation']), 403);

        $engagements = $collecte->engagements()->with(['membre.cellule', 'collecte'])->get()
            ->sortBy(fn (Engagement $engagement): string => $engagement->membre->cellule->nom.' '.$engagement->membre->nom);

        $nom = 'Engagements-'.Str::slug($collecte->titre).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($engagements): void {
            $sortie = fopen('php://output', 'w');

            if ($sortie === false) {
                return;
            }

            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['Matricule', 'Prénom', 'Nom', 'Cellule', 'Téléphone', 'Engagement (FCFA)', 'Versé (FCFA)', 'Reste (FCFA)', 'Avancement (%)', 'Situation', 'Fixé par'], ';');

            foreach ($engagements as $engagement) {
                fputcsv($sortie, [
                    $engagement->membre->matricule,
                    $engagement->membre->prenom,
                    $engagement->membre->nom,
                    $engagement->membre->cellule->nom,
                    $engagement->membre->telephone,
                    $engagement->montant,
                    $engagement->verse(),
                    $engagement->reste(),
                    $engagement->taux(),
                    $engagement->situation(),
                    $engagement->libelleFixePar(),
                ], ';');
            }

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
