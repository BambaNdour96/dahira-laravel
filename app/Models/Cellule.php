<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\CelluleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cellule extends Model
{
    /** @use HasFactory<CelluleFactory> */
    use HasFactory, Journalise;

    protected $fillable = ['nom', 'ville', 'pays', 'responsable_id'];

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return HasMany<Membre, $this> */
    public function membres(): HasMany
    {
        return $this->hasMany(Membre::class);
    }
}
