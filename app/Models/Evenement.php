<?php

namespace App\Models;

use App\Models\Concerns\Annulable;
use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Une activité de la Daara (Magal, ziarra, conférence, réunion), avec la présence des membres.
 *
 * @property int $id
 * @property string $titre
 * @property string|null $description
 * @property string $lieu
 * @property Carbon $debut
 * @property Carbon|null $fin
 * @property int|null $cellule_id
 * @property string $type
 * @property bool $avec_rappel
 * @property int $visiteurs
 * @property int|null $auteur_id
 * @property-read Cellule|null $cellule
 */
class Evenement extends Model
{
    use Annulable, Indelebile, Journalise;

    /** Le contrôle des présences ouvre 3 heures avant le début et ferme 3 heures après la fin. */
    public const MARGE_HEURES = 3;

    public const DAHIRA = 'dahira';

    protected $fillable = ['type', 'titre', 'description', 'lieu', 'debut', 'fin', 'cellule_id', 'avec_rappel', 'visiteurs', 'auteur_id'];

    protected $attributes = ['type' => 'evenement', 'avec_rappel' => true, 'visiteurs' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'debut' => 'datetime',
            'fin' => 'datetime',
            'avec_rappel' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Evenement $evenement): void {
            $identifiant = Auth::id();
            $evenement->auteur_id ??= is_numeric($identifiant) ? (int) $identifiant : null;
        });
    }

    /** Fin de l'événement : l'heure de fin, ou à défaut la fin de la journée. */
    public function finEffective(): Carbon
    {
        return $this->fin ?? $this->debut->copy()->endOfDay();
    }

    public function scanOuvert(): bool
    {
        return ! $this->estAnnule() && now()->between(
            $this->debut->copy()->subHours(self::MARGE_HEURES),
            $this->finEffective()->copy()->addHours(self::MARGE_HEURES),
        );
    }

    public function concerne(Membre $membre): bool
    {
        return $this->cellule_id === null || $this->cellule_id === $membre->cellule_id;
    }

    public function libelleCible(): string
    {
        return $this->cellule !== null ? 'Cellule de '.$this->cellule->nom : 'Toute la Daara';
    }

    /** Nombre de membres validés concernés, pour calculer le taux de participation. */
    public function membresConcernes(): int
    {
        return Membre::query()
            ->where('statut', 'valide')
            ->when($this->cellule_id !== null, fn ($requete) => $requete->where('cellule_id', $this->cellule_id))
            ->count();
    }

    /** @return BelongsTo<Cellule, $this> */
    public function cellule(): BelongsTo
    {
        return $this->belongsTo(Cellule::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function controleurs(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'evenement_controleur');
    }

    /** @return HasMany<Presence, $this> */
    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    /**
     * Les présents seulement (sans les absents excusés).
     *
     * @return HasMany<Presence, $this>
     */
    public function presents(): HasMany
    {
        return $this->hasMany(Presence::class)->where('statut', 'present');
    }

    /** @return HasMany<VisiteSympathisant, $this> */
    public function visitesSympathisants(): HasMany
    {
        return $this->hasMany(VisiteSympathisant::class);
    }

    public function estDahira(): bool
    {
        return $this->type === self::DAHIRA;
    }
}
