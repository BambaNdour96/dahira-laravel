<?php

namespace App\Models;

use App\Models\Concerns\Archivable;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Database\Factories\CelluleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nom
 * @property string $ville
 * @property string $pays
 * @property string|null $whatsapp_lien
 */
class Cellule extends Model
{
    use Archivable, Indelebile;

    /** @use HasFactory<CelluleFactory> */
    use HasFactory, Journalise;

    protected $fillable = ['nom', 'ville', 'pays', 'whatsapp_lien'];

    /** Le Jewrine de la cellule (nomination en cours), s'il y en a un. */
    public function jewrine(): ?User
    {
        return User::query()->whereIn('id', Nomination::query()
            ->where('cellule_id', $this->id)
            ->whereHas('poste', fn (Builder $poste) => $poste->where('code', Poste::JEWRINE_CELLULE))
            ->select('user_id'))->first();
    }

    /**
     * Les cellules dont cette personne est le Jewrine (nomination en cours).
     *
     * @param  Builder<self>  $query
     */
    public function scopeDirigeesPar(Builder $query, User $utilisateur): void
    {
        $query->whereIn($query->qualifyColumn('id'), Nomination::query()
            ->where('user_id', $utilisateur->id)
            ->whereNotNull('cellule_id')
            ->whereHas('poste', fn (Builder $poste) => $poste->where('code', Poste::JEWRINE_CELLULE))
            ->select('cellule_id'));
    }

    /**
     * Les nominations en cours dans cette cellule.
     *
     * @return HasMany<Nomination, $this>
     */
    public function nominations(): HasMany
    {
        return $this->hasMany(Nomination::class);
    }

    /** @return HasMany<Membre, $this> */
    public function membres(): HasMany
    {
        return $this->hasMany(Membre::class);
    }

    /** @return HasMany<CellulePointeur, $this> */
    public function pointeurs(): HasMany
    {
        return $this->hasMany(CellulePointeur::class);
    }
}
