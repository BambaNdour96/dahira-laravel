<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Poste dans l'organigramme de la Daara, attribué par le bureau.
 */
class Poste extends Model
{
    use Journalise;

    protected $fillable = ['nom', 'ordre', 'actif'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'actif' => 'boolean',
        ];
    }

    /** @return HasMany<Membre, $this> */
    public function membres(): HasMany
    {
        return $this->hasMany(Membre::class);
    }
}
