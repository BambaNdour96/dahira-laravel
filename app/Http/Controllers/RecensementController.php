<?php

namespace App\Http\Controllers;

use App\Models\Cellule;
use App\Models\User;
use App\Services\ArabeCoranService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Le recensement Arabe et Coran sur le téléphone, pour la commission Culturelle ; et, pour le Jewrine de cellule, la
 * liste des membres de sa cellule qui souhaitent apprendre (nom et téléphone seulement).
 */
class RecensementController extends Controller
{
    public function index(Request $request, ArabeCoranService $service): View
    {
        abort_unless($service->peutVoirRecensement(self::utilisateur($request)), 403);

        $celluleId = $request->integer('cellule') ?: null;
        $critere = array_key_exists((string) $request->query('critere'), ArabeCoranService::CRITERES) ? (string) $request->query('critere') : null;

        return view('espace.recensement', [
            'chiffres' => $service->chiffresParCellule(),
            'membres' => $service->requete($celluleId, $critere)->paginate(50)->withQueryString(),
            'cellules' => Cellule::query()->enActivite()->orderBy('nom')->pluck('nom', 'id'),
            'celluleId' => $celluleId,
            'critere' => $critere,
        ]);
    }

    public function apprenants(Request $request, ArabeCoranService $service): View
    {
        $utilisateur = self::utilisateur($request);
        abort_unless($service->peutVoirApprenants($utilisateur), 403);

        return view('espace.apprenants', ['apprenants' => $service->apprenants($utilisateur)]);
    }

    private static function utilisateur(Request $request): User
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        return $utilisateur;
    }
}
