<?php

namespace App\Models;

use App\Enums\StatutPaiement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ce qu'un membre doit verser pour une collecte.
 *
 * @property int $id
 * @property int $collecte_id
 * @property int $membre_id
 * @property int $montant
 * @property string $fixe_par
 * @property-read Collecte $collecte
 * @property-read Membre $membre
 */
class Engagement extends Model
{
    protected $fillable = ['collecte_id', 'membre_id', 'montant', 'fixe_par'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['montant' => 'integer'];
    }

    public function verse(): int
    {
        return (int) Paiement::query()
            ->where('collecte_id', $this->collecte_id)
            ->where('membre_id', $this->membre_id)
            ->where('statut', StatutPaiement::Confirme->value)
            ->sum('montant');
    }

    public function reste(): int
    {
        return max(0, $this->montant - $this->verse());
    }

    public function taux(): int
    {
        return $this->montant > 0 ? (int) min(100, round($this->verse() / $this->montant * 100)) : 100;
    }

    /** « À jour », « En retard », « En cours » ou « Aucun versement ». */
    public function situation(): string
    {
        $verse = $this->verse();

        return match (true) {
            $verse >= $this->montant => 'À jour',
            $this->collecte->estEnRetard() => 'En retard',
            $verse > 0 => 'En cours',
            default => 'Aucun versement',
        };
    }

    /** « Jàyante » si le membre l'a fixé lui-même, « Sass » si le bureau l'a fixé. */
    public function nature(): string
    {
        return $this->fixe_par === 'membre' ? 'Jàyante' : 'Sass';
    }

    public function libelleFixePar(): string
    {
        return match ($this->fixe_par) {
            'membre' => 'Le membre',
            'bureau' => 'Le bureau',
            default => 'Automatique',
        };
    }

    /** @return BelongsTo<Collecte, $this> */
    public function collecte(): BelongsTo
    {
        return $this->belongsTo(Collecte::class);
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return HasMany<HistoriqueEngagement, $this> */
    public function historique(): HasMany
    {
        return $this->hasMany(HistoriqueEngagement::class)->latest();
    }
}
