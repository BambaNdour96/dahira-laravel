<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Une annonce de la Daara, avec son affiche, pour tous les membres validés.
 *
 * @property int $id
 * @property string $titre
 * @property string|null $contenu
 * @property string|null $affiche_path
 * @property bool $importante
 * @property Carbon|null $expire_le
 * @property int|null $auteur_id
 * @property Carbon $created_at
 * @property-read User|null $auteur
 */
class Annonce extends Model
{
    use Journalise;

    protected $fillable = ['titre', 'contenu', 'affiche_path', 'importante', 'expire_le', 'auteur_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'importante' => 'boolean',
            'expire_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Annonce $annonce): void {
            $identifiant = Auth::id();
            $annonce->auteur_id ??= is_numeric($identifiant) ? (int) $identifiant : null;
        });

        // L'affiche est supprimée avec l'annonce.
        static::deleted(function (Annonce $annonce): void {
            if ($annonce->affiche_path !== null) {
                Storage::disk('public')->delete($annonce->affiche_path);
            }
        });
    }

    /**
     * Les annonces en cours, les importantes d'abord.
     *
     * @param  Builder<Annonce>  $query
     */
    public function scopeEnCours(Builder $query): void
    {
        $query
            ->where(fn (Builder $duree) => $duree->whereNull('expire_le')->orWhere('expire_le', '>', now()))
            ->orderByDesc('importante')
            ->latest();
    }

    /** Adresse de l'affiche, relative au site, pour fonctionner quelle que soit l'adresse utilisée. */
    public function urlAffiche(): ?string
    {
        return $this->affiche_path !== null ? '/storage/'.$this->affiche_path : null;
    }

    /** @return BelongsTo<User, $this> */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
