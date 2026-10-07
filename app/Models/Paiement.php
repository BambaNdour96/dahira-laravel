<?php

namespace App\Models;

use App\Enums\MoyenPaiement;
use App\Enums\StatutPaiement;
use App\Models\Concerns\Journalise;
use App\Services\RecuService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $membre_id
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
 */
class Paiement extends Model
{
    use Journalise;

    protected $fillable = [
        'membre_id', 'objet', 'montant', 'nombre_mois', 'moyen', 'reference', 'numero_wave', 'statut',
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

    /** Préfixe du numéro de reçu : INS pour l'inscription, COT pour une cotisation. */
    public function prefixeRecu(): string
    {
        return $this->estCotisation() ? 'COT' : 'INS';
    }

    /** « Reçu d'inscription » ou « Reçu de cotisation ». */
    public function natureRecu(): string
    {
        return $this->estCotisation() ? 'Reçu de cotisation' : 'Reçu d\'inscription';
    }

    /** Libellé lisible : « Inscription (frais de carte) » ou « Cotisation (3 mois) ». */
    public function libelleObjet(): string
    {
        return $this->estCotisation()
            ? 'Cotisation ('.$this->nombre_mois.' mois)'
            : 'Inscription (frais de carte)';
    }

    /** Nom du fichier PDF, selon la nature. */
    public function nomFichierRecu(): string
    {
        return ($this->estCotisation() ? 'Recu-cotisation-' : 'Recu-inscription-').$this->numero_recu.'.pdf';
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

    /** @return HasMany<Cotisation, $this> */
    public function cotisations(): HasMany
    {
        return $this->hasMany(Cotisation::class);
    }
}
