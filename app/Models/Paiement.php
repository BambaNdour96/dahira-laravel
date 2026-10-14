<?php

namespace App\Models;

use App\Enums\MoyenPaiement;
use App\Enums\StatutPaiement;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use App\Services\RecuService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $membre_id
 * @property int|null $collecte_id
 * @property string $objet
 * @property int $montant
 * @property int $nombre_mois
 * @property MoyenPaiement $moyen
 * @property string|null $reference
 * @property string|null $numero_wave
 * @property StatutPaiement $statut
 * @property string|null $motif_refus
 * @property Carbon $declare_le
 * @property Carbon|null $traite_le
 * @property int|null $traite_par
 * @property string|null $numero_recu
 * @property-read Membre $membre
 * @property-read Collecte|null $collecte
 */
class Paiement extends Model
{
    use Indelebile, Journalise;

    protected $fillable = [
        'membre_id', 'collecte_id', 'objet', 'montant', 'nombre_mois', 'moyen', 'reference', 'numero_wave', 'statut',
        'motif_refus', 'declare_le', 'traite_le', 'traite_par',
    ];

    protected static function booted(): void
    {
        // Tout paiement confirmé reçoit un numéro de reçu unique, selon sa nature.
        static::saving(function (Paiement $paiement): void {
            if ($paiement->statut === StatutPaiement::Confirme && $paiement->numero_recu === null) {
                $paiement->numero_recu = app(RecuService::class)
                    ->prochainNumero((int) now()->format('Y'), $paiement->prefixeRecu());
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'statut' => StatutPaiement::class,
            'moyen' => MoyenPaiement::class,
            'montant' => 'integer',
            'nombre_mois' => 'integer',
            'declare_le' => 'datetime',
            'traite_le' => 'datetime',
        ];
    }

    public function estCotisation(): bool
    {
        return $this->objet === 'cotisation';
    }

    public function estParticipation(): bool
    {
        return $this->objet === 'collecte';
    }

    /** Préfixe du numéro de reçu : INS (inscription), COT (cotisation), EVT (participation à une collecte). */
    public function prefixeRecu(): string
    {
        return match (true) {
            $this->estCotisation() => 'COT',
            $this->estParticipation() => 'EVT',
            default => 'INS',
        };
    }

    /** « Reçu d'inscription », « Reçu de cotisation » ou « Reçu de participation ». */
    public function natureRecu(): string
    {
        return match (true) {
            $this->estCotisation() => 'Reçu de cotisation',
            $this->estParticipation() => 'Reçu de participation',
            default => 'Reçu d\'inscription',
        };
    }

    /** Libellé lisible : « Inscription (frais de carte) », « Cotisation (3 mois) » ou « Participation : Magal de Touba 2026 ». */
    public function libelleObjet(): string
    {
        return match (true) {
            $this->estCotisation() => 'Cotisation ('.$this->nombre_mois.' mois)',
            $this->estParticipation() => 'Participation : '.($this->collecte !== null ? $this->collecte->titre : 'collecte'),
            default => 'Inscription (frais de carte)',
        };
    }

    /** Nom du fichier PDF, selon la nature. */
    public function nomFichierRecu(): string
    {
        $nature = match (true) {
            $this->estCotisation() => 'Recu-cotisation-',
            $this->estParticipation() => 'Recu-participation-',
            default => 'Recu-inscription-',
        };

        return $nature.$this->numero_recu.'.pdf';
    }

    /** Période couverte par une cotisation : « novembre 2026 – janvier 2027 ». */
    public function periode(): ?string
    {
        if (! $this->estCotisation()) {
            return null;
        }

        $mois = $this->cotisations->sortBy('mois')->pluck('mois');

        if ($mois->isEmpty()) {
            return null;
        }

        $premier = Carbon::parse((string) $mois->first())->translatedFormat('F Y');
        $dernier = Carbon::parse((string) $mois->last())->translatedFormat('F Y');

        return $premier === $dernier ? $premier : $premier.' – '.$dernier;
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return BelongsTo<User, $this> */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par');
    }

    /** @return BelongsTo<Collecte, $this> */
    public function collecte(): BelongsTo
    {
        return $this->belongsTo(Collecte::class);
    }

    /** @return HasMany<Cotisation, $this> */
    public function cotisations(): HasMany
    {
        return $this->hasMany(Cotisation::class);
    }
}
