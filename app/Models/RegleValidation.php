<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Les postes autorisés à valider les paiements d'un poste du bureau.
 *
 * @property int $id
 * @property int $poste_id
 * @property array<int, int|string> $validateurs
 * @property-read Poste $poste
 */
class RegleValidation extends Model
{
    use Journalise;

    protected $table = 'regles_validation';

    protected $fillable = ['poste_id', 'validateurs'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['validateurs' => 'array'];
    }

    protected static function booted(): void
    {
        // Un poste ne peut jamais valider ses propres paiements.
        static::saving(function (RegleValidation $regle): void {
            $regle->validateurs = array_values(array_unique(array_filter(
                array_map('intval', (array) $regle->validateurs),
                fn (int $id): bool => $id !== (int) $regle->poste_id,
            )));
        });
    }

    /** @return list<int> */
    public function idsValidateurs(): array
    {
        return array_values(array_map('intval', (array) $this->validateurs));
    }

    /**
     * Les règles qui s'appliquent au paiement de ce membre : une par poste qu'il occupe.
     *
     * @return Collection<int, self>
     */
    public static function pour(Membre $payeur): Collection
    {
        if ($payeur->user_id === null) {
            return new Collection;
        }

        return self::query()
            ->whereIn('poste_id', Nomination::query()->where('user_id', $payeur->user_id)->select('poste_id'))
            ->get();
    }

    /**
     * Ce validateur est-il autorisé par toutes les règles des postes du payeur ?
     * Sans règle, la validation ordinaire s'applique (Jewrine finance ou représentant).
     */
    public static function autorise(User $validateur, Membre $payeur): bool
    {
        $postesDuValidateur = Nomination::query()->where('user_id', $validateur->id)->pluck('poste_id')->map(fn (mixed $id): int => (int) $id)->all();

        return self::pour($payeur)->every(
            fn (self $regle): bool => array_intersect($postesDuValidateur, $regle->idsValidateurs()) !== [],
        );
    }

    /**
     * Les comptes autorisés par toutes les règles du payeur, sauf le payeur lui-même.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function validateursDe(Membre $payeur): \Illuminate\Support\Collection
    {
        $postes = self::pour($payeur)->flatMap(fn (self $regle): array => $regle->idsValidateurs())->unique()->all();

        return User::query()
            ->whereIn('id', Nomination::query()->whereIn('poste_id', $postes)->select('user_id'))
            ->where('id', '!=', $payeur->user_id)
            ->get()
            ->filter(fn (User $utilisateur): bool => self::autorise($utilisateur, $payeur))
            ->values();
    }

    /**
     * Paiements de membres qui occupent un poste soumis à la validation croisée (ou, avec $soumis à faux, les autres).
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function paiementsSoumis(Builder $query, bool $soumis = true): Builder
    {
        $payeurs = Nomination::query()->whereIn('poste_id', self::query()->select('poste_id'))->select('user_id');
        $filtre = fn (Builder $membre) => $membre->whereIn('user_id', $payeurs);

        return $soumis ? $query->whereHas('membre', $filtre) : $query->whereDoesntHave('membre', $filtre);
    }

    /** « Secrétaire général ou Jewrine national ». */
    public function libelleValidateurs(): string
    {
        return Poste::query()->whereIn('id', $this->idsValidateurs())->orderBy('id')->pluck('nom')->join(', ', ' ou ');
    }

    /** @return BelongsTo<Poste, $this> */
    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }
}
