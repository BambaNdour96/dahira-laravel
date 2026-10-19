<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une carte en plastique commandée et payée : en attente d'un lot, imprimée dans un lot, prête à être retirée, remise.
 *
 * @property int $id
 * @property int $membre_id
 * @property int $paiement_id
 * @property string $statut
 * @property int|null $lot_id
 * @property int|null $carte_id
 * @property Carbon|null $remise_le
 * @property int|null $remise_par
 * @property-read Membre $membre
 * @property-read Paiement $paiement
 * @property-read LotCartes|null $lot
 * @property-read Carte|null $carte
 */
class CartePhysique extends Model
{
    use Indelebile, Journalise;

    public const PAYEE = 'payee';

    public const EN_LOT = 'en_lot';

    public const PRETE = 'prete';

    public const REMISE = 'remise';

    public const LIBELLES = [
        self::PAYEE => 'Payée, en attente d\'impression',
        self::EN_LOT => 'En cours d\'impression',
        self::PRETE => 'Prête : à retirer auprès du Jewrine de cellule',
        self::REMISE => 'Remise',
    ];

    protected $table = 'cartes_physiques';

    protected $attributes = ['statut' => self::PAYEE];

    protected $fillable = ['membre_id', 'paiement_id', 'statut', 'lot_id', 'carte_id', 'remise_le', 'remise_par'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['remise_le' => 'datetime'];
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

    /** @return BelongsTo<LotCartes, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(LotCartes::class, 'lot_id');
    }

    /** @return BelongsTo<Carte, $this> */
    public function carte(): BelongsTo
    {
        return $this->belongsTo(Carte::class);
    }
}
