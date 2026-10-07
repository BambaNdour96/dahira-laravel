<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Demande d'effacement des données personnelles, traitée par le super administrateur.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $membre_id
 * @property string|null $motif
 * @property string $statut
 * @property string|null $reponse
 * @property int|null $traitee_par
 * @property Carbon|null $traitee_le
 * @property Carbon $created_at
 * @property-read User|null $user
 * @property-read Membre|null $membre
 */
class DemandeSuppression extends Model
{
    public const EN_ATTENTE = 'en_attente';

    public const TRAITEE = 'traitee';

    public const REFUSEE = 'refusee';

    protected $table = 'demandes_suppression';

    protected $fillable = ['user_id', 'membre_id', 'motif', 'statut', 'reponse', 'traitee_par', 'traitee_le'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['traitee_le' => 'datetime'];
    }

    public static function libelle(string $statut): string
    {
        return match ($statut) {
            self::EN_ATTENTE => 'En attente',
            self::TRAITEE => 'Données effacées',
            self::REFUSEE => 'Refusée',
            default => $statut,
        };
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return BelongsTo<User, $this> */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traitee_par');
    }
}
