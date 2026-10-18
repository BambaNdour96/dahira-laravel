<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\CommissionComite;
use App\Models\Competence;
use App\Models\Membre;
use App\Models\User;
use App\Policies\MembrePolicy;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Les savoir-faire des membres, pour les mobiliser. Chacun indique les siens ; on les recherche dans toute la Daara,
 * en ne montrant que le nom et la cellule.
 */
final class CompetencesService
{
    public function __construct(private readonly CasquettesService $casquettes) {}

    /** Ses propres savoir-faire, ceux d'un proche dont il gère la fiche, ou ceux d'un membre de sa cellule pour le Jewrine. */
    public function peutModifier(User $utilisateur, Membre $membre): bool
    {
        return $membre->user_id === $utilisateur->id
            || MembrePolicy::gereLaFiche($utilisateur, $membre)
            || $this->casquettes->peut($utilisateur, 'membres.modifier', $membre->cellule_id);
    }

    /**
     * Les savoir-faire choisis remplacent les précédents ; seuls ceux de la liste en activité comptent.
     *
     * @param  list<int>  $competences
     */
    public function definir(Membre $membre, array $competences, User $auteur): void
    {
        if (! $this->peutModifier($auteur, $membre)) {
            throw new DomainException('Vous ne pouvez pas modifier les savoir-faire de ce membre.');
        }

        $valides = Competence::query()->enActivite()->whereKey($competences)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $membre->competences()->sync($valides);
    }

    /** Rechercher par savoir-faire : les chefs de commission d'un comité actif, et ceux qui voient les fiches des membres. */
    public function peutRechercher(User $utilisateur): bool
    {
        return $this->casquettes->peutQuelquePart($utilisateur, 'membres.voir')
            || ($utilisateur->membre !== null && CommissionComite::query()
                ->where('chef_id', $utilisateur->membre->id)
                ->whereHas('instance', fn ($query) => $query->where('statut', 'active'))
                ->exists());
    }

    /**
     * Les membres validés qui ont ce savoir-faire, dans toute la Daara.
     *
     * @return Collection<int, Membre>
     */
    public function rechercher(Competence $competence): Collection
    {
        return Membre::query()
            ->with('cellule')
            ->where('statut', StatutMembre::Valide->value)
            ->whereHas('competences', fn ($query) => $query->whereKey($competence->id))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }

    /**
     * La liste en activité, pour les cases à cocher.
     *
     * @return array<int, string>
     */
    public static function liste(): array
    {
        return Competence::query()->enActivite()->orderBy('ordre')->orderBy('nom')->pluck('nom', 'id')->all();
    }
}
