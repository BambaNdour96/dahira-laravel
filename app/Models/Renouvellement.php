<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Le renouvellement d'un membre pour une campagne : fiche vérifiée, paiement, revalidation si la fiche a changé.
 *
 * @property int $id
 * @property int $campagne_id
 * @property int $membre_id
 * @property string $statut
 * @property string|null $empreinte_fiche
 * @property Carbon|null $fiche_verifiee_le
 * @property int|null $paiement_id
 * @property Carbon|null $renouvele_le
 * @property int|null $revalide_par
 * @property-read CampagneRenouvellement $campagne
 * @property-read Membre $membre
 * @property-read Paiement|null $paiement
 */
class Renouvellement extends Model
{
    use Indelebile, Journalise;

    public const A_FAIRE = 'a_faire';

    public const A_REVALIDER = 'a_revalider';

    public const RENOUVELE = 'renouvele';

    public const D_OFFICE = 'd_office';

    public const NON_RENOUVELE = 'non_renouvele';

    /** Encore à payer : avant la date limite, ou après (« non renouvelé »). */
    public const A_PAYER = [self::A_FAIRE, self::NON_RENOUVELE];

    public const LIBELLES = [
        self::A_FAIRE => 'À faire',
        self::A_REVALIDER => 'Payé, à revalider par le Jewrine',
        self::RENOUVELE => 'Renouvelé',
        self::D_OFFICE => 'Renouvelé d\'office',
        self::NON_RENOUVELE => 'Non renouvelé',
    ];

    protected $fillable = ['campagne_id', 'membre_id', 'statut', 'empreinte_fiche', 'fiche_verifiee_le', 'paiement_id', 'renouvele_le', 'revalide_par'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fiche_verifiee_le' => 'datetime', 'renouvele_le' => 'datetime'];
    }

    public function estFait(): bool
    {
        return in_array($this->statut, [self::RENOUVELE, self::D_OFFICE], true);
    }

    /** @return BelongsTo<CampagneRenouvellement, $this> */
    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagneRenouvellement::class, 'campagne_id');
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }

    /** @return BelongsTo<Paiement, $this> */
    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }
}
