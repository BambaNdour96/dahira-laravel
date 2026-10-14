<?php

namespace App\Http\Controllers;

use App\Models\Evenement;
use App\Models\Presence;
use App\Models\User;
use App\Support\Confidentiel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La liste des présents d'un événement, au format CSV lisible par Excel.
 */
class ExportPresencesController extends Controller
{
    public function __invoke(Request $request, Evenement $evenement): StreamedResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && $utilisateur->can('evenements.voir'), 403);

        $nomFichier = 'presences-'.$evenement->debut->format('Y-m-d').'-'.Str::slug($evenement->titre).'.csv';

        Confidentiel::noter($utilisateur, 'Présences (Excel) : '.$evenement->titre);
        $mention = Confidentiel::mention($utilisateur);

        return response()->streamDownload(function () use ($evenement, $mention): void {
            $sortie = fopen('php://output', 'w');

            if ($sortie === false) {
                return;
            }

            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, ['Matricule', 'Prénom', 'Nom', 'Cellule', 'Heure d\'arrivée', 'Contrôleur'], ';');

            Presence::query()
                ->where('evenement_id', $evenement->id)
                ->with(['membre.cellule', 'controleur'])
                ->orderBy('created_at')
                ->each(function (Presence $presence) use ($sortie): void {
                    fputcsv($sortie, [
                        $presence->membre->matricule,
                        $presence->membre->prenom,
                        $presence->membre->nom,
                        $presence->membre->cellule->nom,
                        $presence->created_at->format('d/m/Y H:i'),
                        $presence->controleur->name ?? '',
                    ], ';');
                });

            fputcsv($sortie, [], ';');
            fputcsv($sortie, [$mention], ';');
            fclose($sortie);
        }, $nomFichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
