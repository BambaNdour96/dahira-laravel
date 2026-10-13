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
use App\Support\AntiFraude;
use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * Le membre déclare son paiement Wave, le Jewrine finance le confirme.
 * Seul un autre membre du bureau (ou plus tard l'API Wave) peut marquer un paiement comme reçu.
 */
final class PaiementService
{
    /** Nombre de déclarations refusées en 30 jours qui déclenche une alerte. */
    public const SEUIL_ALERTE_REFUS = 3;

    public function __construct(
        private readonly StatutMembreService $statuts,
        private readonly CotisationService $cotisations,
    ) {}

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

    /** Déclaration de frais de carte en cours de vérification. */
    public function enCours(Membre $membre): ?Paiement
    {
        return $membre->paiements()
            ->where('objet', 'frais_carte')
            ->where('statut', StatutPaiement::Declare->value)
            ->latest('declare_le')
            ->first();
    }

    public function declarer(Membre $membre, string $reference, ?string $numeroWave = null): Paiement
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
            'numero_wave' => $numeroWave,
            'statut' => StatutPaiement::Declare,
            'declare_le' => now(),
        ]);
    }

    public function confirmer(Paiement $paiement, ?User $tresorier): void
    {
        if ($paiement->statut !== StatutPaiement::Declare) {
            throw new DomainException('Ce paiement a déjà été traité.');
        }

        AntiFraude::verifierValidateur($paiement->membre, $tresorier);

        DB::transaction(function () use ($paiement, $tresorier): void {
            $paiement->update([
                'statut' => StatutPaiement::Confirme,
                'traite_le' => now(),
                'traite_par' => $tresorier?->id,
            ]);

            if ($paiement->objet === 'collecte') {
                app(RappelService::class)->apresVersement($paiement);
            }

            if ($paiement->objet === 'cotisation') {
                $this->cotisations->affecter($paiement);

                return;
            }

            // Versement d'une collecte : il s'ajoute simplement à l'engagement du membre.
            if ($paiement->objet === 'collecte') {
                return;
            }

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

        AntiFraude::verifierValidateur($paiement->membre, $tresorier);

        $paiement->update([
            'statut' => StatutPaiement::Refuse,
            'motif_refus' => $motif,
            'traite_le' => now(),
            'traite_par' => $tresorier?->id,
        ]);

        $this->alerterSiRefusRepetes($paiement->membre);
    }

    public function enregistrerEspeces(Membre $membre, ?User $tresorier): Paiement
    {
        if (! in_array($membre->statut, [StatutMembre::Accepte, StatutMembre::Expire], true)) {
            throw new DomainException('Ce membre n\'attend pas de paiement de frais de carte.');
        }

        AntiFraude::verifierValidateur($membre, $tresorier);

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

    /** Après 3 déclarations refusées en 30 jours, le super administrateur est prévenu. */
    private function alerterSiRefusRepetes(Membre $membre): void
    {
        $refus = Paiement::query()
            ->where('membre_id', $membre->id)
            ->where('statut', StatutPaiement::Refuse->value)
            ->where('traite_le', '>=', now()->subDays(30))
            ->count();

        if ($refus !== self::SEUIL_ALERTE_REFUS) {
            return;
        }

        $superAdmins = User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'super_administrateur'))->get();

        if ($superAdmins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Déclarations de paiement refusées à répétition')
            ->body($membre->prenom.' '.$membre->nom.' a eu '.$refus.' déclarations refusées en 30 jours. À vérifier.')
            ->warning()
            ->sendToDatabase($superAdmins);
    }
}
