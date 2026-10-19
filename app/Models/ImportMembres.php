<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un fichier de reprise des données : analysé (rapport ligne par ligne), puis importé. Le journal note l'analyse et
 * l'import (pas le rapport, trop long).
 *
 * @property int $id
 * @property int $auteur_id
 * @property int|null $cellule_id
 * @property string $fichier_nom
 * @property string $chemin
 * @property string $statut
 * @property int $lignes_total
 * @property int $lignes_pretes
 * @property int $lignes_importees
 * @property list<array{numero: int, etat: string, nom: string, messages: list<string>}>|null $rapport
 * @property Carbon|null $importe_le
 * @property Carbon $created_at
 * @property-read User $auteur
 * @property-read Cellule|null $cellule
 */
class ImportMembres extends Model
{
    use Indelebile;

    public const ANALYSE = 'analyse';

    public const IMPORTE = 'importe';

    /** Les états d'une ligne du rapport. */
    public const PRETE = 'prete';

    public const ERREUR = 'erreur';

    public const DOUBLON = 'doublon';

    public const IMPORTEE = 'importee';

    public const ETATS = [self::PRETE => 'Prête', self::ERREUR => 'Erreur', self::DOUBLON => 'Doublon possible', self::IMPORTEE => 'Importée'];

    protected $table = 'imports_membres';

    protected $attributes = ['statut' => self::ANALYSE];

    protected $fillable = ['auteur_id', 'cellule_id', 'fichier_nom', 'chemin', 'statut', 'lignes_total', 'lignes_pretes', 'lignes_importees', 'rapport', 'importe_le'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['rapport' => 'array', 'importe_le' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    /** @return HasMany<Membre, $this> */
    public function membres(): HasMany
    {
        return $this->hasMany(Membre::class, 'import_id');
    }
}
