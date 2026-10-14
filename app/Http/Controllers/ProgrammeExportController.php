<?php

namespace App\Http\Controllers;

use App\Models\Collecte;
use App\Models\Engagement;
use App\Models\User;
use App\Support\Confidentiel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Les engagements d'un programme, en fichier ouvrable dans Excel (séparateur « ; », accents conservés). */
class ProgrammeExportController extends Controller
{
    public function __invoke(Request $request, Collecte $collecte): StreamedResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && $utilisateur->can('programmes.exporter'), 403);

        $engagements = $collecte->engagements()->with(['membre.cellule', 'collecte'])->get()
            ->sortBy(fn (Engagement $engagement): string => $engagement->membre->cellule->nom.' '.$engagement->membre->nom);

        $avecTelephones = $request->boolean('telephones');
        $motif = trim((string) $request->input('motif'));
        abort_if($avecTelephones && $motif === '', 422, 'Indiquez pourquoi les numéros de téléphone sont nécessaires.');

        Confidentiel::noter($utilisateur, 'Engagements (Excel) : '.$collecte->titre, $avecTelephones ? 'Avec téléphones. Motif : '.$motif : 'Sans téléphones');
        $mention = Confidentiel::mention($utilisateur);

        $nom = 'Engagements-'.Str::slug($collecte->titre).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($engagements, $avecTelephones, $mention): void {
            $sortie = fopen('php://output', 'w');

            if ($sortie === false) {
                return;
            }

            fwrite($sortie, "\xEF\xBB\xBF");
            $entetes = ['Matricule', 'Prénom', 'Nom', 'Cellule', 'Téléphone', 'Engagement (FCFA)', 'Versé (FCFA)', 'Reste (FCFA)', 'Avancement (%)', 'Situation', 'Fixé par'];

            if (! $avecTelephones) {
                unset($entetes[4]);
            }

            fputcsv($sortie, array_values($entetes), ';');

            foreach ($engagements as $engagement) {
                $ligne = [
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
                ];

                if (! $avecTelephones) {
                    unset($ligne[4]);
                }

                fputcsv($sortie, array_values($ligne), ';');
            }

            fputcsv($sortie, [], ';');
            fputcsv($sortie, [$mention], ';');

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
