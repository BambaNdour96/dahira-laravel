<?php

namespace App\Models;

use App\Models\Concerns\Indelebile;
use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un membre se propose pour une commission d'un événement (ou y est invité par son chef), puis la décision.
 * Rien ne s'efface : une proposition retirée ou refusée reste dans l'historique.
 *
 * @property int $id
 * @property int $commission_comite_id
 * @property int $membre_id
 * @property string $statut
 * @property string|null $message
 * @property int|null $invite_par
 * @property Carbon|null $decide_le
 * @property int|null $decide_par
 * @property string|null $motif_refus
 * @property Carbon $created_at
 * @property-read CommissionComite $commission
 * @property-read Membre $membre
 */
class Volontariat extends Model
{
    use Indelebile, Journalise;

    public const PROPOSE = 'propose';

    public const INVITE = 'invite';

    public const ACCEPTE = 'accepte';

    public const REFUSE = 'refuse';

    public const DECLINE = 'decline';

    public const RETIRE = 'retire';

    /** Les propositions et invitations qui attendent une réponse. */
    public const EN_ATTENTE = [self::PROPOSE, self::INVITE];

    public const LIBELLES = [
        self::PROPOSE => 'En attente du chef',
        self::INVITE => 'Invitation à répondre',
        self::ACCEPTE => 'Accepté',
        self::REFUSE => 'Non retenu',
        self::DECLINE => 'Invitation déclinée',
        self::RETIRE => 'Retiré',
    ];

    protected $table = 'volontariats';

    protected $fillable = ['commission_comite_id', 'membre_id', 'statut', 'message', 'invite_par', 'decide_le', 'decide_par', 'motif_refus'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['decide_le' => 'datetime'];
    }

    public function estEnAttente(): bool
    {
        return in_array($this->statut, self::EN_ATTENTE, true);
    }

    /** @return BelongsTo<CommissionComite, $this> */
    public function commission(): BelongsTo
    {
        return $this->belongsTo(CommissionComite::class, 'commission_comite_id');
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
}
