<?php

namespace App\Models;

use App\Enums\TypeSuivi;
use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une entrée du journal d'un projet : point d'avancement, risque, décision ou dépense.
 *
 * @property int $id
 * @property int $projet_id
 * @property TypeSuivi $type
 * @property string $texte
 * @property int|null $montant
 * @property int|null $auteur_id
 * @property Carbon $created_at
 * @property-read User|null $auteur
 */
class SuiviProjet extends Model
{
    use Indelebile;

    protected $table = 'suivis_projet';

    protected $fillable = ['projet_id', 'type', 'texte', 'montant', 'auteur_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => TypeSuivi::class, 'montant' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
