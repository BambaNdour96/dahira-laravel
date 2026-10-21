<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Un type d'événement (Magal, Ziar, Prokhane, Gamou, Laylatoul Qadr…), que le super administrateur complète librement.
 * Un type qui n'a plus cours s'archive : ses événements passés le gardent.
 *
 * @property int $id
 * @property string $nom
 * @property string $code
 * @property int $ordre
 */
class TypeProgramme extends Model
{
    use Archivable, Indelebile, Journalise;

    public const MAGAL = 'magal';

    public const ZIAR = 'ziar';

    public const PROKHANE = 'prokhane';

    protected $table = 'types_programme';

    protected $fillable = ['nom', 'code', 'ordre'];

    protected static function booted(): void
    {
        static::creating(function (TypeProgramme $type): void {
            if (blank($type->code)) {
                $base = Str::slug($type->nom, '_');
                $code = $base;
                for ($i = 2; static::query()->where('code', $code)->exists(); $i++) {
                    $code = $base.'_'.$i;
                }
                $type->code = $code;
            }
        });
    }

    /** Le type par son code, pour les jeux de données et les tests. */
    public static function parCode(string $code): self
    {
        return static::query()->where('code', $code)->firstOrFail();
    }

    /** @return HasMany<Collecte, $this> */
    public function collectes(): HasMany
    {
        return $this->hasMany(Collecte::class);
    }
}
