<?php

namespace App\Http\Controllers;

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Parametre;
use App\Models\Renouvellement;
use App\Models\User;
use App\Services\CotisationService;
use App\Services\PaiementService;
use App\Services\RenouvellementService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EspaceController extends Controller
{
    public function show(Request $request, PaiementService $paiements, CotisationService $cotisations): View
    {
        $user = $request->user();
        $membre = $user instanceof User
            ? $user->membre()->with(['cellule', 'commissions'])->first()
            : null;

        $renouvellement = $membre !== null ? app(RenouvellementService::class)->pour($membre) : null;

        return view('espace.index', [
            'membre' => $membre,
            'renouvellement' => $renouvellement !== null && in_array($renouvellement->statut, Renouvellement::A_PAYER, true) ? $renouvellement : null,
            'whatsappNational' => Parametre::valeur('whatsapp_national'),
            'lienWave' => Parametre::valeur('wave_lien_frais_carte') ?: Parametre::valeur('wave_lien_paiement'),
            'montantFraisCarte' => $paiements->montantFraisCarte(),
            'recuFraisCarte' => $membre?->paiements()->where('objet', 'frais_carte')->where('statut', StatutPaiement::Confirme->value)->whereNotNull('numero_recu')->latest('traite_le')->first(),
            'paiementEnCours' => $membre ? $paiements->enCours($membre) : null,
            'dernierRefus' => $membre?->paiements()
                ->where('objet', 'frais_carte')
                ->where('statut', StatutPaiement::Refuse->value)
                ->latest('traite_le')
                ->first(),
            'retardCotisation' => $membre !== null && $membre->statut === StatutMembre::Valide
                ? count($cotisations->moisEnRetard($membre))
                : 0,
        ]);
    }
}
