<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un ordre de dépense : demandé, signé par le Jewrine national, exécuté par la Finance depuis une caisse avec sa preuve.
 * Celui qui ordonne n'est pas celui qui paie.
 *
 * @property int $id
 * @property string $numero
 * @property string $objet
 * @property int $libelle_depense_id
 * @property int $montant
 * @property int $montant_rendu
 * @property string $nature
 * @property Carbon|null $soldee_le
 * @property string $beneficiaire
 * @property string $rattachement
 * @property int|null $collecte_id
 * @property int|null $commission_comite_id
 * @property int|null $projet_id
 * @property int|null $cellule_id
 * @property int|null $commission_id
 * @property string|null $devis_path
 * @property int $demande_par
 * @property string $statut
 * @property Carbon|null $signe_le
 * @property int|null $signe_par
 * @property bool $signe_par_suppleant
 * @property string|null $motif_refus
 * @property Carbon|null $execute_le
 * @property int|null $execute_par
 * @property int|null $caisse_id
 * @property string|null $preuve_path
 * @property Carbon $created_at
 * @property-read LibelleDepense $libelle
 * @property-read User $demandeur
 * @property-read User|null $signataire
 * @property-read Caisse|null $caisse
 * @property-read Collecte|null $collecte
 * @property-read CommissionComite|null $commissionComite
 * @property-read Projet|null $projet
 * @property-read Cellule|null $cellule
 * @property-read Commission|null $commission
 * @property-read Collection<int, JustificatifDepense> $justificatifs
 */
class OrdreDepense extends Model
{
    use Indelebile, Journalise;

    public const DEMANDE = 'demande';

    public const SIGNE = 'signe';

    public const REFUSE = 'refuse';

    public const EXECUTE = 'execute';

    public const ANNULE = 'annule';

    public const LIBELLES = [
        self::DEMANDE => 'À signer', self::SIGNE => 'Signé, à exécuter', self::REFUSE => 'Refusé', self::EXECUTE => 'Exécuté', self::ANNULE => 'Annulé',
    ];

    public const PAIEMENT = 'paiement';

    public const AVANCE = 'avance';

    public const REMBOURSEMENT = 'remboursement';

    /** La nature de l'ordre : un paiement direct, une avance au chef, ou le remboursement de ce qu'il a payé. */
    public const NATURES = [
        self::PAIEMENT => 'Paiement direct (le fournisseur est payé)',
        self::AVANCE => 'Avance (le chef reçoit l\'argent avant d\'acheter, puis justifie et rend le reste)',
        self::REMBOURSEMENT => 'Remboursement (le chef a payé de sa poche, avec le reçu)',
    ];

    /** Les rattachements : à quoi la dépense est imputée. */
    public const RATTACHEMENTS = [
        'programme' => 'Un programme (et une commission de son comité)',
        'projet' => 'Un projet de la Daara',
        'fonctionnement' => 'Le fonctionnement (la Daara, une cellule ou une commission permanente)',
    ];

    protected $table = 'ordres_depense';

    protected $attributes = ['statut' => self::DEMANDE, 'nature' => self::PAIEMENT, 'montant_rendu' => 0];

    protected $fillable = [
        'numero', 'objet', 'libelle_depense_id', 'montant', 'beneficiaire', 'rattachement', 'nature', 'collecte_id', 'commission_comite_id',
        'projet_id', 'cellule_id', 'commission_id', 'devis_path', 'demande_par', 'statut', 'signe_le', 'signe_par', 'signe_par_suppleant', 'motif_refus',
        'execute_le', 'execute_par', 'caisse_id', 'preuve_path', 'soldee_le',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['signe_le' => 'datetime', 'execute_le' => 'datetime', 'soldee_le' => 'datetime', 'signe_par_suppleant' => 'boolean', 'montant' => 'integer', 'montant_rendu' => 'integer'];
    }

    /**
     * Le total réellement décaissé par des ordres : payés, moins les restes d'avance rendus.
     *
     * @param  Builder<self>  $ordres
     */
    public static function decaisseNet(Builder $ordres): int
    {
        return (int) $ordres->where('statut', self::EXECUTE)->selectRaw('coalesce(sum(montant - montant_rendu), 0) as net')->value('net');
    }

    public function estAvance(): bool
    {
        return $this->nature === self::AVANCE;
    }

    /** Ce qui est réellement sorti des caisses : le montant payé, moins le reste rendu d'une avance. */
    public function decaisse(): int
    {
        return $this->statut === self::EXECUTE ? $this->montant - $this->montant_rendu : 0;
    }

    /** Ce qui est justifié : les pièces d'une avance ; un paiement ou un remboursement l'est par sa preuve. */
    public function justifie(): int
    {
        if ($this->statut !== self::EXECUTE) {
            return 0;
        }

        return $this->estAvance() ? (int) $this->justificatifs()->sum('montant') : $this->montant;
    }

    /** Ce qui reste à justifier ou à rendre sur une avance. */
    public function resteAJustifier(): int
    {
        return max(0, $this->decaisse() - $this->justifie());
    }

    /**
     * Les pièces qui justifient une avance.
     *
     * @return HasMany<JustificatifDepense, $this>
     */
    public function justificatifs(): HasMany
    {
        return $this->hasMany(JustificatifDepense::class);
    }

    /** « Grand Magal · commission Cuisine », « Projet : Daara de Médina », « Fonctionnement · cellule Kaolack »… */
    public function imputation(): string
    {
        return match ($this->rattachement) {
            'programme' => ($this->collecte->titre ?? 'Programme').($this->commissionComite !== null ? ' · commission '.$this->commissionComite->nom : '')
                .($this->commission !== null ? ' · commission '.$this->commission->nom : ''),
            'projet' => 'Projet : '.($this->projet->titre ?? '—'),
            default => 'Fonctionnement'.($this->cellule !== null ? ' · cellule '.$this->cellule->nom : '').($this->commission !== null ? ' · commission '.$this->commission->nom : ''),
        };
    }

    /** @return BelongsTo<LibelleDepense, $this> */
    public function libelle(): BelongsTo
    {
        return $this->belongsTo(LibelleDepense::class, 'libelle_depense_id');
    }

    /** @return BelongsTo<User, $this> */
    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demande_par');
    }

    /** @return BelongsTo<User, $this> */
    public function signataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signe_par');
    }

    /** @return BelongsTo<Caisse, $this> */
    public function caisse(): BelongsTo
    {
        return $this->belongsTo(Caisse::class);
    }

    /** @return BelongsTo<Collecte, $this> */
    public function collecte(): BelongsTo
    {
        return $this->belongsTo(Collecte::class);
    }

    /** @return BelongsTo<CommissionComite, $this> */
    public function commissionComite(): BelongsTo
    {
        return $this->belongsTo(CommissionComite::class);
    }

    /** @return BelongsTo<Projet, $this> */
    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class);
    }

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    /** @return BelongsTo<Commission, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }
}
