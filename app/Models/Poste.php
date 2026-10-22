<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un poste de la Daara. Un poste avec un code donne la casquette du même nom (voir App\Support\Capacites) ;
 * sa portée est nationale ou limitée à une cellule ; un seul titulaire l'occupe à la fois, sauf exception.
 *
 * @property int $id
 * @property string $nom
 * @property string|null $code
 * @property string $perimetre
 * @property bool $titulaire_unique
 * @property int $ordre
 * @property bool $actif
 */
class Poste extends Model
{
    use Journalise;

    public const JEWRINE_NATIONAL = 'jewrine_national';

    public const JEWRINE_CELLULE = 'jewrine_cellule';

    public const JEWRINE_COMMISSION = 'jewrine_commission';

    public const CONVOCATEUR_COMITE_DIRECTEUR = 'convocateur_comite_directeur';

    /** Le Jewrine du comité de suivi des dépenses d'un comité d'organisation (nommé depuis l'espace du comité). */
    public const JEWRINE_SUIVI_DEPENSES = 'jewrine_suivi_depenses';

    /** Son suppléant, qui signe quand le Jewrine est indisponible (une seule suppléance active par comité). */
    public const SUPPLEANT_SUIVI_DEPENSES = 'suppleant_suivi_depenses';

    /** Les postes que seul le super administrateur attribue. */
    public const RESERVES_AU_SUPER_ADMINISTRATEUR = [self::JEWRINE_NATIONAL, self::JEWRINE_COMMISSION, self::CONVOCATEUR_COMITE_DIRECTEUR];

    public const PERIMETRES = ['national' => 'Toute la Daara', 'cellule' => 'Une cellule', 'commission' => 'Une commission permanente', 'comite' => 'Un comité d\'organisation'];

    protected $fillable = ['nom', 'ordre', 'actif', 'perimetre', 'titulaire_unique'];

    protected $attributes = ['perimetre' => 'national', 'titulaire_unique' => true, 'actif' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'actif' => 'boolean',
            'titulaire_unique' => 'boolean',
        ];
    }

    public function estDeCellule(): bool
    {
        return $this->perimetre === 'cellule';
    }

    public function estDeCommission(): bool
    {
        return $this->perimetre === 'commission';
    }

    public function estDeComite(): bool
    {
        return $this->perimetre === 'comite';
    }

    public static function parCode(string $code): ?self
    {
        return self::query()->where('code', $code)->first();
    }

    /**
     * Les nominations en cours sur ce poste.
     *
     * @return HasMany<Nomination, $this>
     */
    public function nominations(): HasMany
    {
        return $this->hasMany(Nomination::class);
    }
}
