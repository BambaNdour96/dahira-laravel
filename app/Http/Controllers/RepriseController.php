<?php

namespace App\Http\Controllers;

use App\Models\ImportMembres;
use App\Models\User;
use App\Services\ImportMembresService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Les fichiers de la reprise des données : le modèle à remplir, et le rapport d'un fichier analysé. */
class RepriseController extends Controller
{
    public function modele(Request $request, ImportMembresService $service): Response
    {
        $this->autoriser($request, $service);

        return response(ImportMembresService::modeleCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-reprise-membres.csv"',
        ]);
    }

    public function rapport(Request $request, ImportMembres $import, ImportMembresService $service): Response
    {
        $utilisateur = $this->autoriser($request, $service);
        abort_unless($utilisateur->can('membres.importer') || $import->auteur_id === $utilisateur->id, 403);

        return response($service->rapportCsv($import), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rapport-reprise-'.$import->id.'.csv"',
        ]);
    }

    private function autoriser(Request $request, ImportMembresService $service): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && $service->peutImporter($utilisateur), 403);

        return $utilisateur;
    }
}
