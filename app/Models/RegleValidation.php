<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Les postes autorisés à valider les paiements d'un poste du bureau.
 *
 * @property int $id
 * @property int $poste_id
 * @property array<int, int|string> $validateurs
 * @property-read Poste $poste
 */
class RegleValidation extends Model
{
    protected $table = 'regles_validation';

    protected $fillable = ['poste_id', 'validateurs'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['validateurs' => 'array'];
    }

    protected static function booted(): void
    {
        // Un poste ne peut jamais valider ses propres paiements.
        static::saving(function (RegleValidation $regle): void {
            $regle->validateurs = array_values(array_unique(array_filter(
                array_map('intval', (array) $regle->validateurs),
                fn (int $id): bool => $id !== (int) $regle->poste_id,
            )));
        });
    }

    /** @return list<int> */
    public function idsValidateurs(): array
    {
        return array_values(array_map('intval', (array) $this->validateurs));
    }

    /** « Secrétaire général ou Jewrine national ». */
    public function libelleValidateurs(): string
    {
        return Poste::query()->whereIn('id', $this->idsValidateurs())->orderBy('id')->pluck('nom')->join(', ', ' ou ');
    }

    /** @return BelongsTo<Poste, $this> */
    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }
}
