<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une réaffectation de fonds (le solde du Magal vers un projet, par exemple) : décidée par le Jewrine national,
 * enregistrée par la Finance, avec son origine. L'argent ne change pas de caisse : il change d'affectation.
 *
 * @property int $id
 * @property string $numero
 * @property int $montant
 * @property string $motif
 * @property string $origine
 * @property int|null $origine_collecte_id
 * @property int|null $origine_projet_id
 * @property string $destination
 * @property int|null $destination_collecte_id
 * @property int|null $destination_projet_id
 * @property string $statut
 * @property int $decidee_par
 * @property Carbon|null $enregistree_le
 * @property int|null $enregistree_par
 * @property Carbon $created_at
 * @property-read Collecte|null $origineCollecte
 * @property-read Projet|null $origineProjet
 * @property-read Collecte|null $destinationCollecte
 * @property-read Projet|null $destinationProjet
 * @property-read User $decideur
 */
class ReaffectationFonds extends Model
{
    use Indelebile, Journalise;

    public const DECIDEE = 'decidee';

    public const ENREGISTREE = 'enregistree';

    public const ANNULEE = 'annulee';

    public const LIBELLES = [self::DECIDEE => 'Décidée, à enregistrer', self::ENREGISTREE => 'Enregistrée', self::ANNULEE => 'Annulée'];

    /** D'où vient et où va l'argent. */
    public const FONDS = ['programme' => 'Un programme', 'projet' => 'Un projet', 'fonctionnement' => 'Le fonctionnement (caisse commune)'];

    protected $table = 'reaffectations_fonds';

    protected $attributes = ['statut' => self::DECIDEE];

    protected $fillable = [
        'numero', 'montant', 'motif', 'origine', 'origine_collecte_id', 'origine_projet_id', 'destination', 'destination_collecte_id',
        'destination_projet_id', 'statut', 'decidee_par', 'enregistree_le', 'enregistree_par',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enregistree_le' => 'datetime', 'montant' => 'integer'];
    }

    public function libelleOrigine(): string
    {
        return self::fonds($this->origine, $this->origineCollecte, $this->origineProjet);
    }

    public function libelleDestination(): string
    {
        return self::fonds($this->destination, $this->destinationCollecte, $this->destinationProjet);
    }

    private static function fonds(string $type, ?Collecte $collecte, ?Projet $projet): string
    {
        return match ($type) {
            'programme' => 'Programme : '.($collecte->titre ?? '—'),
            'projet' => 'Projet : '.($projet->titre ?? '—'),
            default => 'Fonctionnement',
        };
    }

    /** @return BelongsTo<Collecte, $this> */
    public function origineCollecte(): BelongsTo
    {
        return $this->belongsTo(Collecte::class, 'origine_collecte_id');
    }

    /** @return BelongsTo<Projet, $this> */
    public function origineProjet(): BelongsTo
    {
        return $this->belongsTo(Projet::class, 'origine_projet_id');
    }

    /** @return BelongsTo<Collecte, $this> */
    public function destinationCollecte(): BelongsTo
    {
        return $this->belongsTo(Collecte::class, 'destination_collecte_id');
    }

    /** @return BelongsTo<Projet, $this> */
    public function destinationProjet(): BelongsTo
    {
        return $this->belongsTo(Projet::class, 'destination_projet_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidee_par');
    }
}
