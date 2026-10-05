<?php

namespace App\Models;

use App\Enums\TypeTarif;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarif extends Model
{
    use Journalise;

    protected $fillable = ['type', 'libelle', 'montant', 'exercice_id', 'actif'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => TypeTarif::class,
            'montant' => 'integer',
            'actif' => 'boolean',
        ];
    }

    /** @return BelongsTo<Exercice, $this> */
    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
}
