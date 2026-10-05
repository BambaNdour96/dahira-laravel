<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Database\Factories\CelluleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nom
 * @property string $ville
 * @property string $pays
 * @property int|null $responsable_id
 * @property string|null $whatsapp_lien
 */
class Cellule extends Model
{
    /** @use HasFactory<CelluleFactory> */
    use HasFactory, Journalise;

    protected $fillable = ['nom', 'ville', 'pays', 'responsable_id', 'whatsapp_lien'];

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
