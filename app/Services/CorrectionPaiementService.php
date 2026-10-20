<?php

namespace App\Services;

use App\Enums\MoyenPaiement;
use App\Enums\StatutPaiement;
use App\Enums\TypeCaisse;
use App\Models\Avoir;
use App\Models\Caisse;
use App\Models\Collecte;
use App\Models\CorrectionPaiement;
use App\Models\Cotisation;
use App\Models\JournalActivite;
use App\Models\LibelleDepense;
use App\Models\MouvementCaisse;
use App\Models\OrdreDepense;
use App\Models\Paiement;
use App\Models\User;
use App\Observers\AvisMembreObserver;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Les corrections d'un paiement déjà validé, réservées au Jewrine finance national (le super administrateur en
 * secours). Rien ne s'efface : la contre-passation annule par une écriture inverse dans la caisse ; la réaffectation
 * remplace le paiement par un paiement nouveau, l'argent restant dans sa caisse ; l'avoir fait un crédit au membre ; le
 * remboursement passe par un ordre de dépense. Motif obligatoire, membre prévenu, tout est journalisé.
 */
final class CorrectionPaiementService
{
    /** Les corrections portent sur les cotisations et les versements des programmes. */
    public const OBJETS_CORRIGEABLES = ['cotisation', 'collecte'];

    public function __construct(private readonly CotisationService $cotisations) {}

    public static function peutCorriger(User $utilisateur, Paiement $paiement): bool
    {
        return $utilisateur->can('paiements.corriger')
            && $paiement->statut === StatutPaiement::Confirme
            && in_array($paiement->objet, self::OBJETS_CORRIGEABLES, true)
            && ! CorrectionPaiement::query()->where('paiement_id', $paiement->id)->where('statut', CorrectionPaiement::EN_COURS)->exists();
    }

    /** L'erreur est annulée par une écriture inverse : l'argent sort de la caisse où il était entré. */
    public function contrePasser(Paiement $paiement, string $motif, User $auteur): CorrectionPaiement
    {
        $this->verifier($paiement, $motif, $auteur);

        return DB::transaction(function () use ($paiement, $motif, $auteur): CorrectionPaiement {
            if ($paiement->moyen !== MoyenPaiement::Avoir) {
                $caisse = $this->caisseDeRetrait($paiement);

                if ($caisse->solde() < $paiement->montant) {
                    throw new DomainException('Solde insuffisant dans « '.$caisse->nom.' » pour annuler ce paiement ('.CollecteService::fcfa($caisse->solde()).').');
                }

                MouvementCaisse::query()->create([
                    'caisse_id' => $caisse->id, 'sens' => MouvementCaisse::SORTIE, 'montant' => $paiement->montant, 'nature' => 'correction',
                    'libelle' => 'Contre-passation de '.($paiement->numero_recu ?? '#'.$paiement->id).' · '.trim($motif), 'paiement_id' => $paiement->id,
                    'auteur_id' => $auteur->id, 'date_operation' => now(),
                ]);
            } elseif ($paiement->avoir !== null) {
                // Un paiement par avoir annulé rend son montant à l'avoir.
                $paiement->avoir->increment('reste', $paiement->montant);
            }

            $this->annuler($paiement, $motif, $auteur);
            $correction = $this->tracer($paiement, 'contre_passation', $motif, $auteur);
            $this->prevenir($paiement, 'Paiement annulé', 'Votre paiement '.($paiement->numero_recu ?? '').' de '.CollecteService::fcfa($paiement->montant).' est annulé : '.trim($motif).'.');

            return $correction;
        });
    }

    /**
     * Le paiement change d'objet (une cotisation devient un Jàyante, un versement passe à un autre programme…) : il est
     * remplacé par un paiement nouveau, avec son reçu ; l'argent reste dans sa caisse.
     *
     * @param  array{objet: string, collecte_id?: ?int}  $cible
     */
    public function reaffecter(Paiement $paiement, array $cible, string $motif, User $auteur): CorrectionPaiement
    {
        $this->verifier($paiement, $motif, $auteur);

        return DB::transaction(function () use ($paiement, $cible, $motif, $auteur): CorrectionPaiement {
            $this->annuler($paiement, $motif, $auteur);
            $nouveau = $this->nouveauPaiement($paiement, $cible, $paiement->montant, $paiement->moyen, $paiement->caisse_id, null, $auteur);
            $correction = $this->tracer($paiement, 'reaffectation', $motif, $auteur, ['nouveau_paiement_id' => $nouveau->id]);
            $this->prevenir($paiement, 'Paiement réaffecté', 'Votre paiement de '.CollecteService::fcfa($paiement->montant).' compte désormais pour : '.$nouveau->libelleObjet().'. Nouveau reçu : '.$nouveau->numero_recu.'.');

            return $correction;
        });
    }

    /** Le paiement devient un crédit sur le compte du membre ; l'argent reste dans sa caisse. */
    public function versAvoir(Paiement $paiement, string $motif, User $auteur): Avoir
    {
        $this->verifier($paiement, $motif, $auteur);

        return DB::transaction(function () use ($paiement, $motif, $auteur): Avoir {
            $this->annuler($paiement, $motif, $auteur);
            $avoir = Avoir::query()->create([
                'membre_id' => $paiement->membre_id, 'montant' => $paiement->montant, 'reste' => $paiement->montant,
                'motif' => mb_substr(trim($motif), 0, 255), 'paiement_origine_id' => $paiement->id, 'auteur_id' => $auteur->id,
            ]);
            $this->tracer($paiement, 'avoir', $motif, $auteur, ['avoir_id' => $avoir->id]);
            $this->prevenir($paiement, 'Avoir sur votre compte', 'Votre paiement de '.CollecteService::fcfa($paiement->montant).' devient un avoir : la Finance l\'imputera sur un prochain paiement.');

            return $avoir;
        });
    }

    /**
     * La Finance impute à la main tout ou partie d'un avoir sur un paiement du membre (cotisation, Jàyante).
     *
     * @param  array{objet: string, collecte_id?: ?int}  $cible
     */
    public function imputerAvoir(Avoir $avoir, array $cible, int $montant, User $auteur): Paiement
    {
        if (! $auteur->can('paiements.corriger')) {
            throw new DomainException('Seul le Jewrine finance national impute un avoir.');
        }

        if ($montant < 1 || $montant > $avoir->reste) {
            throw new DomainException('Le montant imputé va de 1 à '.CollecteService::fcfa($avoir->reste).'.');
        }

        return DB::transaction(function () use ($avoir, $cible, $montant, $auteur): Paiement {
            $origine = $avoir->origine;
            $paiement = $this->nouveauPaiement($origine ?? new Paiement(['membre_id' => $avoir->membre_id]), $cible, $montant, MoyenPaiement::Avoir, null, $avoir, $auteur);
            $avoir->decrement('reste', $montant);
            JournalActivite::enregistrer($avoir, 'modifie', ['apres' => ['imputation' => CollecteService::fcfa($montant).' sur '.$paiement->libelleObjet()]]);
            AvisMembreObserver::avertir($avoir->membre, 'Avoir imputé', CollecteService::fcfa($montant).' de votre avoir réglent : '.$paiement->libelleObjet().'. Reçu : '.$paiement->numero_recu.'.', route('recus.index'), 'Mes reçus');

            return $paiement;
        });
    }

    /** Le remboursement passe par un ordre de dépense ; le paiement n'est annulé qu'une fois l'ordre payé. */
    public function rembourser(Paiement $paiement, string $motif, User $auteur): CorrectionPaiement
    {
        $this->verifier($paiement, $motif, $auteur);
        $libelle = LibelleDepense::query()->where('nom', 'Remboursement')->first() ?? LibelleDepense::query()->create(['nom' => 'Remboursement', 'ordre' => 20]);

        return DB::transaction(function () use ($paiement, $motif, $auteur, $libelle): CorrectionPaiement {
            $ordre = app(OrdreDepenseService::class)->demander([
                'objet' => 'Remboursement de '.($paiement->numero_recu ?? 'paiement #'.$paiement->id).' · '.trim($motif), 'libelle_depense_id' => $libelle->id,
                'montant' => $paiement->montant, 'beneficiaire' => $paiement->membre->nom_complet, 'rattachement' => 'fonctionnement',
                'cellule_id' => $paiement->membre->cellule_id,
            ], null, $auteur);

            return $this->tracer($paiement, 'remboursement', $motif, $auteur, ['ordre_depense_id' => $ordre->id, 'statut' => CorrectionPaiement::EN_COURS]);
        });
    }

    /** L'ordre de remboursement est payé : le paiement est annulé (l'argent est sorti par l'ordre) et le membre prévenu. */
    public function apresOrdre(OrdreDepense $ordre): void
    {
        $correction = CorrectionPaiement::query()->where('ordre_depense_id', $ordre->id)->where('statut', CorrectionPaiement::EN_COURS)->first();

        if ($correction === null) {
            return;
        }

        if ($ordre->statut !== OrdreDepense::EXECUTE) {
            $correction->update(['statut' => CorrectionPaiement::ABANDONNEE]);

            return;
        }

        $paiement = $correction->paiement;
        $auteur = $ordre->execute_par !== null ? User::query()->whereKey($ordre->execute_par)->first() : null;
        $this->annuler($paiement, $correction->motif, $auteur);
        $correction->update(['statut' => CorrectionPaiement::FAITE]);
        $this->prevenir($paiement, 'Paiement remboursé', 'Votre paiement de '.CollecteService::fcfa($paiement->montant).' vous est remboursé ('.$ordre->numero.').');
    }

    private function verifier(Paiement $paiement, string $motif, User $auteur): void
    {
        if (! $auteur->can('paiements.corriger')) {
            throw new DomainException('Seul le Jewrine finance national corrige un paiement validé.');
        }

        if (! self::peutCorriger($auteur, $paiement)) {
            throw new DomainException('Ce paiement ne peut pas être corrigé (seuls les cotisations et les versements validés, sans correction en cours).');
        }

        if (trim($motif) === '') {
            throw new DomainException('Le motif de la correction est obligatoire.');
        }
    }

    /** La caisse d'où sort l'argent : celle du paiement, ou la caisse générale si le représentant a déjà remis ses espèces. */
    private function caisseDeRetrait(Paiement $paiement): Caisse
    {
        $caisse = $paiement->caisse ?? app(TresorerieService::class)->caissePour($paiement);

        return $caisse->type === TypeCaisse::Representant && $paiement->remise_id !== null
            ? app(TresorerieService::class)->caisse(TypeCaisse::Especes)
            : $caisse;
    }

    /** Le paiement passe à « annulé » ; ses mois de cotisation sont marqués annulés et redeviennent dus. */
    private function annuler(Paiement $paiement, string $motif, ?User $auteur): void
    {
        Cotisation::query()->where('paiement_id', $paiement->id)->update(['annulee_le' => now()]);
        $paiement->forceFill([
            'statut' => StatutPaiement::Contrepasse, 'contrepasse_le' => now(), 'contrepasse_par' => $auteur?->id, 'motif_correction' => mb_substr(trim($motif), 0, 255),
        ])->save();
    }

    /**
     * Un paiement nouveau, validé, pour l'objet choisi.
     *
     * @param  array{objet: string, collecte_id?: ?int}  $cible
     */
    private function nouveauPaiement(Paiement $modele, array $cible, int $montant, MoyenPaiement $moyen, ?int $caisseId, ?Avoir $avoir, User $auteur): Paiement
    {
        $objet = $cible['objet'];
        $nombreMois = 0;
        $collecteId = null;

        if ($objet === 'cotisation') {
            $mensuel = $this->cotisations->montantMensuel();

            if ($montant % $mensuel !== 0) {
                throw new DomainException('Pour une cotisation, le montant doit couvrir des mois entiers ('.CollecteService::fcfa($mensuel).' par mois).');
            }

            $nombreMois = intdiv($montant, $mensuel);
        } elseif ($objet === 'collecte') {
            $collecte = Collecte::query()->whereKey($cible['collecte_id'] ?? 0)->first() ?? throw new DomainException('Choisissez le programme.');
            $engagement = app(CollecteService::class)->engagementDe($collecte, $modele->membre);

            if ($engagement === null || $montant > $engagement->reste()) {
                throw new DomainException('Le membre n\'a pas de Jàyante pour ce programme, ou le montant dépasse ce qui lui reste à verser.');
            }

            $collecteId = $collecte->id;
        } else {
            throw new DomainException('Réaffectez vers une cotisation ou un programme.');
        }

        $paiement = Paiement::query()->create([
            'membre_id' => $modele->membre_id, 'cellule_id' => $modele->cellule_id, 'objet' => $objet, 'collecte_id' => $collecteId,
            'montant' => $montant, 'nombre_mois' => $nombreMois, 'moyen' => $moyen, 'caisse_id' => $caisseId, 'avoir_id' => $avoir?->id,
            'statut' => StatutPaiement::Confirme, 'declare_le' => $modele->declare_le ?? now(), 'declare_par' => $modele->declare_par,
            'traite_le' => now(), 'traite_par' => $auteur->id,
        ]);

        if ($objet === 'cotisation') {
            $this->cotisations->affecter($paiement);
        }

        return $paiement;
    }

    /**
     * @param  array<string, mixed>  $autres
     */
    private function tracer(Paiement $paiement, string $type, string $motif, User $auteur, array $autres = []): CorrectionPaiement
    {
        return CorrectionPaiement::query()->create([
            'paiement_id' => $paiement->id, 'type' => $type, 'montant' => $paiement->montant, 'motif' => mb_substr(trim($motif), 0, 255), 'auteur_id' => $auteur->id, ...$autres,
        ]);
    }

    private function prevenir(Paiement $paiement, string $titre, string $message): void
    {
        AvisMembreObserver::avertir($paiement->membre, $titre, $message, route('recus.index'), 'Mes reçus');
    }
}
