<?php

namespace App\Models;

use App\Enums\NiveauComprehension;
use App\Enums\NiveauCoran;
use App\Enums\NiveauKhassaides;
use App\Enums\NiveauLecture;
use App\Models\Concerns\Indelebile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La rubrique « Arabe et Coran » d'un membre. Confidentielle : seuls le membre (ou celui qui gère sa fiche), la commission
 * Culturelle et le super administrateur la voient. Elle n'est pas journalisée en détail, pour ne pas la répandre.
 *
 * @property int $id
 * @property int $membre_id
 * @property NiveauLecture|null $lecture
 * @property NiveauLecture|null $ecriture
 * @property NiveauComprehension|null $comprehension
 * @property NiveauCoran|null $coran
 * @property int|null $nombre_hizb
 * @property NiveauKhassaides|null $khassaides
 * @property bool|null $souhaite_apprendre
 * @property int|null $renseigne_par
 * @property-read Membre $membre
 */
class ProfilArabeCoran extends Model
{
    use Indelebile;

    protected $table = 'profils_arabe_coran';

    protected $fillable = ['membre_id', 'lecture', 'ecriture', 'comprehension', 'coran', 'nombre_hizb', 'khassaides', 'souhaite_apprendre', 'renseigne_par'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'lecture' => NiveauLecture::class, 'ecriture' => NiveauLecture::class, 'comprehension' => NiveauComprehension::class,
            'coran' => NiveauCoran::class, 'khassaides' => NiveauKhassaides::class, 'souhaite_apprendre' => 'boolean', 'nombre_hizb' => 'integer',
        ];
    }

    /** La rubrique est remplie dès que la lecture est indiquée. */
    public function estRemplie(): bool
    {
        return $this->lecture !== null;
    }

    /** « 12 hizb », « Hafiz », « Quelques sourates »… */
    public function libelleCoran(): ?string
    {
        return match ($this->coran) {
            NiveauCoran::Hizb => $this->nombre_hizb.' hizb',
            null => null,
            default => $this->coran->getLabel(),
        };
    }

    /** @return BelongsTo<Membre, $this> */
    public function membre(): BelongsTo
    {
        return $this->belongsTo(Membre::class);
    }
}
