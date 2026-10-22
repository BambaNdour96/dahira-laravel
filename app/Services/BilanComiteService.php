<?php

namespace App\Services;

use App\Enums\StatutCollecte;
use App\Models\BilanComite;
use App\Models\Collecte;
use App\Models\CommissionComite;
use App\Models\Instance;
use App\Models\OrdreDepense;
use App\Models\Reunion;
use App\Models\User;
use App\Notifications\NotificationMembre;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * La clôture d'un comité d'organisation : chaque chef rend le bilan de sa commission (chiffres figés, objectifs
 * atteints ou non, forces, faiblesses, améliorations), le président le bilan général, Suivi et Évaluation son
 * évaluation ; le président choisit la réunion de bilan. La clôture est possible quand tous les bilans sont rendus et
 * toutes les avances justifiées ; le comité reste ensuite en archive, en lecture seule. Les améliorations notées
 * s'affichent à la mise en place du comité suivant du même type.
 */
final class BilanComiteService
{
    public function __construct(private readonly SuiviDepensesService $suivi) {}

    /**
     * Les chiffres d'une commission, figés dans son bilan.
     *
     * @return array<string, int>
     */
    public function chiffresCommission(CommissionComite $commission): array
    {
        $taches = $commission->actions()->where('statut', '!=', 'abandonnee')->get();

        return [
            'budget' => $commission->budget(), 'decaisse' => $commission->depense(), 'justifie' => $commission->justifie(),
            'reste_a_justifier' => $commission->resteAJustifier(), 'taches' => $taches->count(), 'taches_faites' => $taches->where('statut', 'faite')->count(),
        ];
    }

    /**
     * Les chiffres du comité, figés dans le bilan général et l'évaluation.
     *
     * @return array<string, int>
     */
    public function chiffresComite(Instance $comite): array
    {
        $argent = $this->suivi->indicateurs($comite);

        return [
            'promis' => $argent['promis'], 'encaisse' => $argent['encaisse'], 'decaisse' => $argent['decaisse'], 'disponible' => $argent['disponible'],
            'enveloppe' => $argent['enveloppe'] ?? 0, 'taches' => $argent['taches'], 'taches_faites' => $argent['taches_faites'],
            'reunions' => $comite->reunions()->count(), 'decisions' => $comite->decisions()->count(),
        ];
    }

    private function verifierOuvert(Instance $comite): void
    {
        if (! $comite->estComiteOrganisation() || $comite->estClos()) {
            throw new DomainException('Ce comité est clos : il reste en archive, en lecture seule.');
        }
    }

    public function peutRendreBilanCommission(User $utilisateur, CommissionComite $commission): bool
    {
        return $commission->instance->estComiteOrganisation() && ! $commission->instance->estClos() && ComiteService::dirige($utilisateur, $commission);
    }

    /**
     * Le chef rend (ou complète) le bilan de sa commission.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function rendreBilanCommission(CommissionComite $commission, array $donnees, User $auteur): BilanComite
    {
        $this->verifierOuvert($commission->instance);

        if (! $this->peutRendreBilanCommission($auteur, $commission)) {
            throw new DomainException('Le chef de la commission rend son bilan.');
        }

        $bilan = $this->enregistrer($commission->instance, $commission, BilanComite::COMMISSION, $donnees, $this->chiffresCommission($commission), $auteur);
        $this->prevenir($commission->instance->president?->user, $auteur, 'Bilan rendu : commission '.$commission->nom, $commission->instance);

        return $bilan;
    }

    public function peutRendreBilanGeneral(User $utilisateur, Instance $comite): bool
    {
        return $comite->estComiteOrganisation() && ! $comite->estClos() && ComiteService::peutGerer($utilisateur, $comite);
    }

    /**
     * Le président rend (ou complète) le bilan général.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function rendreBilanGeneral(Instance $comite, array $donnees, User $auteur): BilanComite
    {
        $this->verifierOuvert($comite);

        if (! $this->peutRendreBilanGeneral($auteur, $comite)) {
            throw new DomainException('Le président du comité rend le bilan général.');
        }

        return $this->enregistrer($comite, null, BilanComite::GENERAL, $donnees, $this->chiffresComite($comite), $auteur);
    }

    public function peutEvaluer(User $utilisateur, Instance $comite): bool
    {
        return $comite->estComiteOrganisation() && ! $comite->estClos() && $utilisateur->can('comites.evaluer');
    }

    /**
     * Suivi et Évaluation rend (ou complète) son évaluation.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function evaluer(Instance $comite, array $donnees, User $auteur): BilanComite
    {
        $this->verifierOuvert($comite);

        if (! $this->peutEvaluer($auteur, $comite)) {
            throw new DomainException('La commission Suivi et Évaluation rend l\'évaluation.');
        }

        $bilan = $this->enregistrer($comite, null, BilanComite::EVALUATION, $donnees, $this->chiffresComite($comite), $auteur);
        $this->prevenir($comite->president?->user, $auteur, 'Évaluation rendue : '.$comite->nom, $comite);

        return $bilan;
    }

    /**
     * Un bilan par commission, un bilan général, une évaluation : rendu une fois, complété ensuite, chiffres refigés.
     *
     * @param  array<string, mixed>  $donnees
     * @param  array<string, int>  $chiffres
     */
    private function enregistrer(Instance $comite, ?CommissionComite $commission, string $type, array $donnees, array $chiffres, User $auteur): BilanComite
    {
        $appreciation = (string) ($donnees['appreciation'] ?? '');

        if (! array_key_exists($appreciation, BilanComite::APPRECIATIONS)) {
            throw new DomainException('Dites si les objectifs sont atteints, en partie, ou non.');
        }

        $texte = fn (string $cle): ?string => filled($donnees[$cle] ?? null) ? mb_substr(trim((string) $donnees[$cle]), 0, 5000) : null;

        if ($texte('forces') === null && $texte('faiblesses') === null && $texte('ameliorations') === null) {
            throw new DomainException('Écrivez au moins les forces, les faiblesses ou les améliorations.');
        }

        return DB::transaction(function () use ($comite, $commission, $type, $appreciation, $texte, $chiffres, $auteur): BilanComite {
            $bilan = BilanComite::query()->where('instance_id', $comite->id)->where('type', $type)
                ->where('commission_comite_id', $commission?->id)->lockForUpdate()->first()
                ?? new BilanComite(['instance_id' => $comite->id, 'commission_comite_id' => $commission?->id, 'type' => $type]);

            $bilan->fill([
                'appreciation' => $appreciation, 'forces' => $texte('forces'), 'faiblesses' => $texte('faiblesses'),
                'ameliorations' => $texte('ameliorations'), 'chiffres' => $chiffres, 'auteur_id' => $auteur->id,
            ])->save();

            return $bilan;
        });
    }

    /** Le président choisit, parmi les réunions du comité, la réunion de bilan. */
    public function choisirReunionBilan(Instance $comite, Reunion $reunion, User $auteur): void
    {
        $this->verifierOuvert($comite);

        if (! ComiteService::peutGerer($auteur, $comite) || $reunion->instance_id !== $comite->id) {
            throw new DomainException('Le président choisit la réunion de bilan parmi celles du comité.');
        }

        $comite->update(['reunion_bilan_id' => $reunion->id]);
    }

    /**
     * Ce qu'il faut pour clôturer : chaque bilan, l'évaluation, les avances justifiées (la réunion de bilan est indiquée).
     *
     * @return list<array{libelle: string, fait: bool, obligatoire: bool}>
     */
    public function conditions(Instance $comite): array
    {
        $bilans = $comite->bilans()->get();
        $conditions = [];

        foreach ($comite->commissions()->get() as $commission) {
            $conditions[] = ['libelle' => 'Bilan de la commission '.$commission->nom, 'fait' => $bilans->where('commission_comite_id', $commission->id)->isNotEmpty(), 'obligatoire' => true];
        }

        $conditions[] = ['libelle' => 'Bilan général du président', 'fait' => $bilans->where('type', BilanComite::GENERAL)->isNotEmpty(), 'obligatoire' => true];
        $conditions[] = ['libelle' => 'Évaluation de Suivi et Évaluation', 'fait' => $bilans->where('type', BilanComite::EVALUATION)->isNotEmpty(), 'obligatoire' => true];
        $avances = $this->avancesNonSoldees($comite);
        $conditions[] = ['libelle' => $avances === 0 ? 'Toutes les avances justifiées' : $avances.' avance(s) à justifier ou à rendre', 'fait' => $avances === 0, 'obligatoire' => true];
        $conditions[] = ['libelle' => $comite->reunionBilan !== null ? 'Réunion de bilan : '.$comite->reunionBilan->titre : 'Réunion de bilan à choisir', 'fait' => $comite->reunion_bilan_id !== null, 'obligatoire' => false];

        return $conditions;
    }

    private function avancesNonSoldees(Instance $comite): int
    {
        return OrdreDepense::query()->where('nature', OrdreDepense::AVANCE)->where('statut', OrdreDepense::EXECUTE)->whereNull('soldee_le')
            ->where(fn ($query) => $query->whereIn('commission_comite_id', CommissionComite::query()->where('instance_id', $comite->id)->select('id'))
                ->when($comite->collecte_id !== null, fn ($evenement) => $evenement->orWhere('collecte_id', $comite->collecte_id)))
            ->count();
    }

    public function peutCloturer(User $utilisateur, Instance $comite): bool
    {
        return $comite->estComiteOrganisation() && ! $comite->estClos() && ComiteService::peutGerer($utilisateur, $comite);
    }

    /** Clôturer : quand tout est rendu et justifié ; le comité passe en archive, en lecture seule. */
    public function cloturer(Instance $comite, User $auteur): void
    {
        if (! $this->peutCloturer($auteur, $comite)) {
            throw new DomainException('Le président du comité (ou le Jewrine national) clôture le comité.');
        }

        $manque = array_values(array_filter($this->conditions($comite), fn (array $condition): bool => $condition['obligatoire'] && ! $condition['fait']));

        if ($manque !== []) {
            throw new DomainException('Pas encore : '.implode(' ; ', array_map(fn (array $condition): string => mb_lcfirst($condition['libelle']), $manque)).'.');
        }

        $comite->update(['statut' => 'close', 'cloture_le' => now(), 'cloture_par' => $auteur->id]);

        foreach (app(ComiteService::class)->comptesDesMembres($comite) as $membre) {
            $this->prevenir($membre, $auteur, 'Comité clos : '.$comite->nom, $comite, 'Le comité est clos : ses bilans et son archive restent consultables.');
        }

        if ($this->evenementAClore($comite) !== null) {
            $auteur->notify(new NotificationMembre(
                'Clôturer l\'événement : '.$comite->collecte?->titre,
                'Le comité est clos. Clôturez maintenant l\'événement lié : son solde rejoindra la caisse générale.',
                route('filament.admin.resources.collectes.view', $comite->collecte_id),
                'Clôturer l\'événement',
            ));
        }
    }

    /** L'événement lié au comité, s'il est encore ouvert : à clôturer à son tour (et son solde à verser). */
    public function evenementAClore(Instance $comite): ?Collecte
    {
        $collecte = $comite->collecte;

        return $collecte !== null && $collecte->statut === StatutCollecte::Ouverte ? $collecte : null;
    }

    /**
     * Les améliorations notées au dernier comité clos du même type d'événement, à revoir à la mise en place.
     *
     * @return list<array{source: string, texte: string}>
     */
    public function ameliorationsPrecedentes(Instance $comite): array
    {
        if (! $comite->estComiteOrganisation() || $comite->estClos()) {
            return [];
        }

        $type = $comite->collecte?->type_programme_id;
        $precedent = Instance::query()->where('type', Instance::COMITE_ORGANISATION)->where('statut', 'close')->whereKeyNot($comite->id)
            ->when($type !== null, fn ($query) => $query->whereHas('collecte', fn ($collecte) => $collecte->where('type_programme_id', $type)), fn ($query) => $query->where('code', $comite->code))
            ->orderByDesc('cloture_le')->first();

        if ($precedent === null) {
            return [];
        }

        return array_values($precedent->bilans()->with('commission')->whereNotNull('ameliorations')->get()
            ->map(fn (BilanComite $bilan): array => ['source' => $precedent->nom.' · '.$bilan->titre(), 'texte' => (string) $bilan->ameliorations])->all());
    }

    private function prevenir(?User $destinataire, User $auteur, string $titre, Instance $comite, string $message = 'Il est consultable dans l\'onglet « Bilan » de l\'espace du comité.'): void
    {
        if ($destinataire !== null && $destinataire->id !== $auteur->id) {
            $destinataire->notify(new NotificationMembre($titre, $message, route('filament.admin.resources.instances.view', $comite), 'Ouvrir l\'espace'));
        }
    }
}
