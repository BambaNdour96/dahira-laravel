<?php

namespace App\Http\Controllers;

use App\Models\OrdreDepense;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Le devis ou la preuve d'un ordre de dépense : pour son demandeur et pour ceux qui suivent les dépenses. */
class PieceOrdreDepenseController extends Controller
{
    public function __invoke(Request $request, OrdreDepense $ordre, string $piece): StreamedResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User && ($ordre->demande_par === $utilisateur->id
            || $utilisateur->can('depenses.signer') || $utilisateur->can('depenses.executer') || $utilisateur->can('finances.voir')), 403);

        $chemin = $piece === 'devis' ? $ordre->devis_path : ($piece === 'preuve' ? $ordre->preuve_path : null);
        abort_unless(is_string($chemin) && Storage::disk('local')->exists($chemin), 404);

        return Storage::disk('local')->response($chemin);
    }
}
