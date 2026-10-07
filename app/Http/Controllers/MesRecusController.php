<?php

namespace App\Http\Controllers;

use App\Enums\StatutPaiement;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Tous les reçus du membre, filtrés par nature et par année, 10 par page.
 */
class MesRecusController extends Controller
{
    public function index(Request $request): View
    {
        $utilisateur = $request->user();
        $membre = $utilisateur instanceof User ? $utilisateur->membre : null;

        abort_if($membre === null, 404);

        $type = in_array($request->query('type'), ['inscription', 'cotisation'], true) ? (string) $request->query('type') : null;
        $annee = is_numeric($request->query('annee')) ? (int) $request->query('annee') : null;

        $base = Paiement::query()
            ->where('membre_id', $membre->id)
            ->where('statut', StatutPaiement::Confirme->value)
            ->whereNotNull('numero_recu');

        $annees = (clone $base)
            ->pluck('traite_le')
            ->filter()
            ->map(fn ($date): int => Carbon::parse((string) $date)->year)
            ->unique()
            ->sortDesc()
            ->values();

        $recus = (clone $base)
            ->with('cotisations')
            ->when($type !== null, fn ($requete) => $requete->where('objet', $type === 'inscription' ? 'frais_carte' : 'cotisation'))
            ->when($annee !== null, fn ($requete) => $requete->whereYear('traite_le', (string) $annee))
            ->latest('traite_le')
            ->paginate(10)
            ->withQueryString();

        return view('espace.recus', [
            'recus' => $recus,
            'annees' => $annees,
            'type' => $type,
            'annee' => $annee,
            'total' => (int) (clone $base)->sum('montant'),
        ]);
    }
}
