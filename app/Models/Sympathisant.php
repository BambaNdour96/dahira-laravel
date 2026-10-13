<?php

namespace App\Models;

use App\Enums\Sexe;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un sympathisant : une personne proche de la Daara, sans compte ni carte.
 *
 * @property int $id
 * @property string $prenom
 * @property string $nom
 * @property Sexe $sexe
 * @property int $cellule_id
 * @property string|null $telephone
 * @property int|null $invite_par_id
 * @property int|null $membre_id
 * @property Carbon $consentement_le
 * @property Carbon|null $derniere_visite_le
 * @property int|null $cree_par
 * @property-read Cellule $cellule
 * @property-read Membre|null $invitePar
 * @property-read Membre|null $membre
 */
class Sympathisant extends Model
{
    /** Un sympathisant venu 3 fois en 3 mois est invité à devenir membre. */
    public const VISITES_POUR_INVITER = 3;

    protected $fillable = [
        'prenom', 'nom', 'sexe', 'cellule_id', 'telephone', 'invite_par_id', 'membre_id',
        'consentement_le', 'derniere_visite_le', 'cree_par',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'sexe' => Sexe::class,
            'consentement_le' => 'datetime',
            'derniere_visite_le' => 'datetime',
        ];
    }

    public function nomComplet(): string
    {
        return $this->prenom.' '.$this->nom;
    }

    public function visitesRecentes(): int
    {
        return $this->visites()->where('created_at', '>=', now()->subMonths(3))->count();
    }

    public function aInviter(): bool
    {
        return $this->membre_id === null && $this->visitesRecentes() >= self::VISITES_POUR_INVITER;
    }

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    /** @return BelongsTo<Membre, $this> */
    public function invitePar(): BelongsTo
    {
        return $this->belongsTo(Membre::class, 'invite_par_id');
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return HasMany<VisiteSympathisant, $this> */
    public function visites(): HasMany
    {
        return $this->hasMany(VisiteSympathisant::class);
    }

    /** @return HasMany<ParticipationSympathisant, $this> */
    public function participations(): HasMany
    {
        return $this->hasMany(ParticipationSympathisant::class);
    }
}
