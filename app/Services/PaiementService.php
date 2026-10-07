<?php

namespace App\Services;

use App\Enums\MoyenPaiement;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Enums\TypeTarif;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Tarif;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Frais de carte : le membre déclare son paiement Wave, le Jewrine finance le confirme.
 * Seul le Jewrine finance (ou plus tard l'API Wave) peut marquer un paiement comme reçu.
 */
final class PaiementService
{
    public function __construct(private readonly StatutMembreService $statuts) {}

    /** Montant fixé par le bureau dans Paramètres → Tarifs. */
    public function montantFraisCarte(): int
    {
        $montant = Tarif::query()
            ->where('type', TypeTarif::Adhesion->value)
            ->whereNull('exercice_id')
            ->where('actif', true)
            ->value('montant');

        return is_numeric($montant) ? (int) $montant : 1000;
    }

    public function enCours(Membre $membre): ?Paiement
    {
        return $membre->paiements()
            ->where('statut', StatutPaiement::Declare->value)
            ->latest('declare_le')
            ->first();
    }

    public function declarer(Membre $membre, string $reference): Paiement
    {
        if ($membre->statut !== StatutMembre::Accepte) {
            throw new DomainException('Votre demande doit d\'abord être acceptée par votre Jewrine de cellule.');
        }

        if ($this->enCours($membre) !== null) {
            throw new DomainException('Un paiement est déjà en cours de vérification.');
        }

        return $membre->paiements()->create([
            'objet' => 'frais_carte',
            'montant' => $this->montantFraisCarte(),
            'moyen' => MoyenPaiement::Wave,
            'reference' => $reference,
            'statut' => StatutPaiement::Declare,
            'declare_le' => now(),
        ]);
    }

    public function confirmer(Paiement $paiement, ?User $tresorier): void
    {
        if ($paiement->statut !== StatutPaiement::Declare) {
            throw new DomainException('Ce paiement a déjà été traité.');
        }

        DB::transaction(function () use ($paiement, $tresorier): void {
            $paiement->update([
                'statut' => StatutPaiement::Confirme,
                'traite_le' => now(),
                'traite_par' => $tresorier?->id,
            ]);

            $membre = $paiement->membre;

            if ($membre->statut !== StatutMembre::Valide) {
                $this->statuts->changer($membre, StatutMembre::Valide, $tresorier);
            }
        });
    }

    public function refuser(Paiement $paiement, ?User $tresorier, string $motif): void
    {
        if ($paiement->statut !== StatutPaiement::Declare) {
            throw new DomainException('Ce paiement a déjà été traité.');
        }

        $paiement->update([
            'statut' => StatutPaiement::Refuse,
            'motif_refus' => $motif,
            'traite_le' => now(),
            'traite_par' => $tresorier?->id,
        ]);
    }

    public function enregistrerEspeces(Membre $membre, ?User $tresorier): Paiement
    {
        if (! in_array($membre->statut, [StatutMembre::Accepte, StatutMembre::Expire], true)) {
            throw new DomainException('Ce membre n\'attend pas de paiement de frais de carte.');
        }

        return DB::transaction(function () use ($membre, $tresorier): Paiement {
            $paiement = $membre->paiements()->create([
                'objet' => 'frais_carte',
                'montant' => $this->montantFraisCarte(),
                'moyen' => MoyenPaiement::Especes,
                'statut' => StatutPaiement::Confirme,
                'declare_le' => now(),
                'traite_le' => now(),
                'traite_par' => $tresorier?->id,
            ]);

            $this->statuts->changer($membre, StatutMembre::Valide, $tresorier);

            return $paiement;
        });
    }
}
