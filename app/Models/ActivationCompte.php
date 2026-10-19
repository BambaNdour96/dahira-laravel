<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une demande d'activation de compte d'un membre sans matricule connu : le Jewrine de cellule la valide.
 * Le mot de passe choisi est gardé chiffré jusqu'à la décision.
 *
 * @property int $id
 * @property int $membre_id
 * @property string $telephone
 * @property string $mot_de_passe
 * @property string $statut
 * @property Carbon|null $decide_le
 * @property int|null $decide_par
 * @property Carbon $created_at
 * @property-read Membre $membre
 */
class ActivationCompte extends Model
{
    use Indelebile, Journalise;

    public const EN_ATTENTE = 'en_attente';

    public const VALIDEE = 'validee';

    public const REFUSEE = 'refusee';

    public const LIBELLES = [self::EN_ATTENTE => 'En attente', self::VALIDEE => 'Validée', self::REFUSEE => 'Refusée'];

    protected $table = 'activations_compte';

    protected $attributes = ['statut' => self::EN_ATTENTE];

    protected $fillable = ['membre_id', 'telephone', 'mot_de_passe', 'statut', 'decide_le', 'decide_par'];

    protected $hidden = ['mot_de_passe'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['decide_le' => 'datetime'];
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
}
