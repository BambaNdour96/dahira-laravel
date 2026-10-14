<?php

namespace App\Services;

use App\Enums\Sexe;
use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\CellulePointeur;
use App\Models\Evenement;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\Presence;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Qui peut pointer qui, et comment.
 * - Jewrine de cellule : tous les membres de sa cellule ; une Sokhna est alors « pointée en secours ».
 * - Jewrine des Sokhnas : les Sokhnas de sa cellule.
 * - Pointeurs désignés : les Goor Yalla ou les Sokhnas de leur cellule, selon leur périmètre.
 * - Contrôleurs d'un événement : tout le monde.
 * - Super administrateur et Jewrine national : tout le monde, et corrections après coup.
 */
final class PointageService
{
    /** Le périmètre de cette personne pour cet événement : « tous », « hommes », « femmes », ou null. */
    public function perimetre(User $utilisateur, Evenement $evenement): ?string
    {
        if ($utilisateur->can('pointage.partout')) {
            return 'tous';
        }

        if ($evenement->controleurs()->where('users.id', $utilisateur->id)->exists()) {
            return 'tous';
        }

        $cellule = $evenement->cellule;

        if ($cellule === null) {
            return null;
        }

        if (app(CasquettesService::class)->estJewrineDe($utilisateur, $cellule->id)) {
            return 'tous';
        }

        if ($cellule->jewrine_femmes_id === $utilisateur->id) {
            return 'femmes';
        }

        $pointeur = CellulePointeur::query()->where('cellule_id', $cellule->id)->where('user_id', $utilisateur->id)->first();

        return $pointeur?->perimetre;
    }

    /** Peut-il pointer maintenant (ou corriger après coup, pour la direction) ? */
    public function peutPointer(User $utilisateur, Evenement $evenement): bool
    {
        if ($this->perimetre($utilisateur, $evenement) === null) {
            return false;
        }

        return $evenement->scanOuvert() || $this->peutCorriger($utilisateur);
    }

    public function peutCorriger(User $utilisateur): bool
    {
        return $utilisateur->can('pointage.partout');
    }

    /**
     * Vérifie que cette personne peut pointer ce membre ; renvoie « secours » si un Goor Yalla pointe une Sokhna.
     */
    public function autorisation(User $utilisateur, Evenement $evenement, Membre $membre): ?string
    {
        $perimetre = $this->perimetre($utilisateur, $evenement);

        if ($perimetre === null) {
            throw new DomainException('Vous ne pouvez pas pointer les présences de ce rendez-vous.');
        }

        if (! $evenement->scanOuvert() && ! $this->peutCorriger($utilisateur)) {
            throw new DomainException('La liste de présence est close. Seuls le Jewrine national et le super administrateur peuvent la corriger.');
        }

        if ($membre->statut !== StatutMembre::Valide || ! $evenement->concerne($membre)) {
            throw new DomainException('Ce membre n\'est pas concerné par ce rendez-vous.');
        }

        if ($perimetre === 'hommes' && $membre->sexe !== Sexe::Homme) {
            throw new DomainException('Les Sokhnas sont pointées par la Jewrine des Sokhnas ou une pointeuse.');
        }

        if ($perimetre === 'femmes' && $membre->sexe !== Sexe::Femme) {
            throw new DomainException('Les Goor Yalla sont pointés par le Jewrine ou un pointeur.');
        }

        $cellule = $evenement->cellule;

        return $cellule !== null
            && app(CasquettesService::class)->estJewrineDe($utilisateur, $cellule->id)
            && $membre->sexe === Sexe::Femme
            && $evenement->estSeance() ? 'secours' : null;
    }

    /** Pointe un membre présent ou absent excusé ; un nouveau pointage remplace l'ancien. */
    public function pointer(Evenement $evenement, Membre $membre, User $utilisateur, string $mode = 'scan', string $statut = 'present', ?string $motif = null): Presence
    {
        $secours = $this->autorisation($utilisateur, $evenement, $membre);

        if ($statut === 'excuse' && blank($motif)) {
            throw new DomainException('Indiquez le motif de l\'absence.');
        }

        return Presence::query()->updateOrCreate(
            ['evenement_id' => $evenement->id, 'membre_id' => $membre->id],
            [
                'enregistree_par' => $utilisateur->id,
                'mode' => $secours ?? $mode,
                'statut' => $statut,
                'motif' => $statut === 'excuse' ? mb_substr((string) $motif, 0, 150) : null,
            ],
        );
    }

    public function retirer(Evenement $evenement, Membre $membre, User $utilisateur): void
    {
        $this->autorisation($utilisateur, $evenement, $membre);

        $presence = Presence::query()->where('evenement_id', $evenement->id)->where('membre_id', $membre->id)->first();

        if ($presence === null) {
            return;
        }

        // Une correction de pointage : la présence s'efface, la trace reste au journal.
        $presence->delete();
        JournalActivite::enregistrer($evenement, 'modifie', [
            'avant' => ['pointage' => $membre->prenom.' '.$membre->nom.' ('.($presence->statut === 'excuse' ? 'excusé(e)' : 'présent(e)').')'],
            'apres' => ['pointage' => 'retiré'],
        ]);
    }

    /**
     * Les rendez-vous que cette personne peut pointer en ce moment.
     *
     * @return Collection<int, Evenement>
     */
    public function rendezVousDuMoment(User $utilisateur): Collection
    {
        return Evenement::query()
            ->with('cellule')
            ->where('debut', '<=', now()->addHours(Evenement::MARGE_HEURES))
            ->where('debut', '>=', now()->subDays(2))
            ->orderBy('debut')
            ->get()
            ->filter(fn (Evenement $evenement): bool => $evenement->scanOuvert() && $this->perimetre($utilisateur, $evenement) !== null)
            ->values();
    }

    /**
     * Les membres concernés, dans le périmètre de cette personne.
     *
     * @return Collection<int, Membre>
     */
    public function membresAPointer(Evenement $evenement, User $utilisateur): Collection
    {
        $perimetre = $this->perimetre($utilisateur, $evenement);

        return Membre::query()
            ->where('statut', StatutMembre::Valide->value)
            ->when($evenement->cellule_id !== null, fn ($requete) => $requete->where('cellule_id', $evenement->cellule_id))
            ->when($perimetre === 'hommes', fn ($requete) => $requete->where('sexe', Sexe::Homme->value))
            ->when($perimetre === 'femmes', fn ($requete) => $requete->where('sexe', Sexe::Femme->value))
            ->orderBy('nom')
            ->orderBy('prenom')
            ->get();
    }

    /**
     * Le bilan d'un rendez-vous.
     *
     * @return array{concernes: int, presents: int, freres: int, soeurs: int, excuses: int, absents: int, taux: int, sympathisants: int, visiteurs: int, total: int}
     */
    public function bilan(Evenement $evenement): array
    {
        $presences = $evenement->presences()->with('membre')->get();
        $presents = $presences->where('statut', 'present');
        $concernes = $evenement->membresConcernes();
        $excuses = $presences->where('statut', 'excuse')->count();
        $sympathisants = $evenement->visitesSympathisants()->count();

        return [
            'concernes' => $concernes,
            'presents' => $presents->count(),
            'freres' => $presents->filter(fn (Presence $presence): bool => $presence->membre->sexe === Sexe::Homme)->count(),
            'soeurs' => $presents->filter(fn (Presence $presence): bool => $presence->membre->sexe === Sexe::Femme)->count(),
            'excuses' => $excuses,
            'absents' => max(0, $concernes - $presents->count() - $excuses),
            'taux' => $concernes > 0 ? (int) round($presents->count() / $concernes * 100) : 0,
            'sympathisants' => $sympathisants,
            'visiteurs' => $evenement->visiteurs,
            'total' => $presents->count() + $sympathisants + $evenement->visiteurs,
        ];
    }
}
