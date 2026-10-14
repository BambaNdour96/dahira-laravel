<?php

namespace App\Http\Controllers;

use App\Models\Carte;
use App\Models\Connexion;
use App\Models\Cotisation;
use App\Models\JournalActivite;
use App\Models\Nomination;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Droit d'accès : la personne télécharge toutes les données que la Daara détient sur elle.
 * Les secrets (mot de passe, double authentification, code du QR) n'y figurent jamais.
 */
class MesDonneesController extends Controller
{
    public function telecharger(Request $request): JsonResponse
    {
        $utilisateur = $request->user();
        abort_unless($utilisateur instanceof User, 403);

        $membre = $utilisateur->membre()->with(['cellule', 'commissions'])->first();

        $donnees = [
            'avertissement' => 'Copie des données personnelles détenues par la Daara Nouroud Daareyni Kaolack, '
                .'établie le '.now()->format('d/m/Y à H:i').'.',
            'compte' => $utilisateur->only(['name', 'telephone', 'email', 'created_at', 'derniere_connexion_at']),
            'fiche_membre' => null,
            'paiements' => [],
            'cotisations' => [],
            'cartes' => [],
            'connexions' => [],
        ];

        if ($membre !== null) {
            $donnees['fiche_membre'] = collect($membre->attributesToArray())
                ->except(['id', 'user_id', 'cellule_id', 'photo_path'])
                ->merge([
                    'cellule' => $membre->cellule->nom,
                    'responsabilites' => $utilisateur->nominations()->withoutGlobalScope(Nomination::EN_COURS)->with(['poste', 'cellule'])->get()
                        ->map(fn (Nomination $nomination): array => [
                            'poste' => $nomination->libelle(),
                            'depuis' => $nomination->debut_le->format('d/m/Y'),
                            'fin' => $nomination->fin_le?->format('d/m/Y'),
                        ])->all(),
                    'commissions' => $membre->commissions->pluck('nom')->all(),
                    'photo' => $membre->photo_path ? 'Photo enregistrée (visible sur votre carte de membre).' : 'Aucune photo.',
                ])
                ->all();

            $donnees['paiements'] = Paiement::query()
                ->where('membre_id', $membre->id)
                ->orderBy('declare_le')
                ->get()
                ->map(fn (Paiement $paiement): array => [
                    'objet' => $paiement->libelleObjet(),
                    'montant_fcfa' => $paiement->montant,
                    'moyen' => $paiement->moyen->getLabel(),
                    'reference' => $paiement->reference,
                    'numero_wave' => $paiement->numero_wave,
                    'statut' => $paiement->statut->getLabel(),
                    'declare_le' => $paiement->declare_le->toIso8601String(),
                    'traite_le' => $paiement->traite_le?->toIso8601String(),
                    'numero_recu' => $paiement->numero_recu,
                ])
                ->all();

            $donnees['cotisations'] = Cotisation::query()
                ->where('membre_id', $membre->id)
                ->orderBy('mois')
                ->get()
                ->map(fn (Cotisation $cotisation): array => [
                    'mois' => $cotisation->mois->format('Y-m'),
                    'montant_fcfa' => $cotisation->montant,
                    'dispense' => (bool) $cotisation->getAttribute('exoneree'),
                    'motif' => $cotisation->getAttribute('motif'),
                ])
                ->all();

            $donnees['cartes'] = Carte::query()
                ->where('membre_id', $membre->id)
                ->get()
                ->map(fn (Carte $carte): array => collect($carte->attributesToArray())->except(['id', 'membre_id', 'jeton'])->all())
                ->all();
        }

        if (Schema::hasColumn('connexions', 'user_id')) {
            $donnees['connexions'] = Connexion::query()
                ->where('user_id', $utilisateur->id)
                ->latest()
                ->limit(500)
                ->get()
                ->map(fn (Connexion $connexion): array => collect($connexion->attributesToArray())->except(['id', 'user_id'])->all())
                ->all();
        }

        JournalActivite::enregistrer($utilisateur, 'export_donnees', ['apres' => ['format' => 'JSON']]);

        return response()->json(
            $donnees,
            200,
            ['Content-Disposition' => 'attachment; filename="mes-donnees-daara-ndk.json"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
