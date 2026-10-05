<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\CommissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Commission extends Model
{
    /** @use HasFactory<CommissionFactory> */
    use HasFactory, Journalise;

    /** Un membre appartient à 2 commissions au maximum. */
    public const MAX_PAR_MEMBRE = 2;

    protected $fillable = ['nom', 'description', 'actif'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    /** @return BelongsToMany<Membre, $this> */
    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(Membre::class)->withTimestamps();
    }
}
