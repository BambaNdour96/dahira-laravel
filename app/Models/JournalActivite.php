<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * Une ligne du journal : qui a fait quoi, sur quel objet, et quand.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $sujet_type
 * @property int $sujet_id
 * @property string $evenement
 * @property array<string, array<string, mixed>>|null $changements
 */
class JournalActivite extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'journal_activites';

    protected $fillable = ['user_id', 'sujet_type', 'sujet_id', 'evenement', 'changements'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'changements' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @param  array<string, array<string, mixed>>  $changements */
    public static function enregistrer(Model $sujet, string $evenement, array $changements = []): void
    {
        static::query()->create([
            'user_id' => Auth::id(),
            'sujet_type' => $sujet->getMorphClass(),
            'sujet_id' => $sujet->getKey(),
            'evenement' => $evenement,
            'changements' => $changements === [] ? null : $changements,
        ]);
    }

    /** Trace l'arrivée ou le départ d'un membre dans un groupe (comité, commission) : le lien s'efface, la trace reste. */
    public static function appartenance(Model $groupe, string $libelle, Membre $membre, bool $ajoute): void
    {
        self::enregistrer($groupe, 'modifie', [
            'avant' => [$libelle => null],
            'apres' => [$libelle => $membre->prenom.' '.$membre->nom.($ajoute ? ' ajouté(e)' : ' retiré(e)')],
        ]);
    }

    /** Résumé lisible des changements, pour l'écran du journal. */
    public function resume(): string
    {
        if ($this->evenement === 'cree') {
            return 'Fiche créée';
        }

        if ($this->evenement === 'supprime') {
            return 'Fiche supprimée';
        }

        if ($this->evenement === 'anonymise') {
            return 'Données personnelles effacées ; le reste est conservé';
        }

        $parties = [];

        foreach ($this->changements['apres'] ?? [] as $champ => $valeur) {
            $avant = $this->changements['avant'][$champ] ?? null;
            $parties[] = $champ.' : '.self::texte($avant).' → '.self::texte($valeur);
        }

        return implode(' ; ', $parties);
    }

    private static function texte(mixed $valeur): string
    {
        if ($valeur === null || $valeur === '') {
            return '∅';
        }

        return is_scalar($valeur) ? (string) $valeur : (json_encode($valeur) ?: '');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function sujet(): MorphTo
    {
        return $this->morphTo();
    }
}
