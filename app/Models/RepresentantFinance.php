<?php

namespace App\Models;

use App\Enums\Sexe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Le représentant du Jewrine finance dans une cellule : un Goor Yalla pour les Goor Yalla, une Sokhna pour les Sokhnas.
 *
 * @property int $id
 * @property int $cellule_id
 * @property int $user_id
 * @property string $perimetre
 * @property int|null $designe_par
 * @property-read Cellule $cellule
 * @property-read User $user
 */
class RepresentantFinance extends Model
{
    public const PERIMETRES = ['hommes' => 'Les Goor Yalla', 'femmes' => 'Les Sokhnas'];

    protected $table = 'representants_finance';

    protected $fillable = ['cellule_id', 'user_id', 'perimetre', 'designe_par'];

    public function sexe(): Sexe
    {
        return $this->perimetre === 'femmes' ? Sexe::Femme : Sexe::Homme;
    }

    /** Ce représentant couvre-t-il ce membre (même cellule, même périmètre) ? */
    public function couvre(Membre $membre): bool
    {
        return $membre->cellule_id === $this->cellule_id && $membre->sexe === $this->sexe();
    }

    /** Le représentant de ce membre, s'il y en a un. */
    public static function pour(Membre $membre): ?self
    {
        return self::query()
            ->where('cellule_id', $membre->cellule_id)
            ->where('perimetre', $membre->sexe === Sexe::Femme ? 'femmes' : 'hommes')
            ->first();
    }

    /** Cette personne représente-t-elle le Jewrine finance auprès de ce membre ? */
    public static function represente(User $utilisateur, Membre $membre): bool
    {
        $representant = self::pour($membre);

        return $representant !== null && $representant->user_id === $utilisateur->id;
    }

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
