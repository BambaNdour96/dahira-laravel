<?php

namespace App\Services;

use App\Enums\StatutPaiement;
use App\Models\CommissionComite;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\Nomination;
use App\Models\OrdreDepense;
use App\Models\Poste;
use App\Models\PropositionSuiviDepenses;
use App\Models\RevisionBudget;
use App\Models\User;
use App\Notifications\NotificationMembre;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Le comité de suivi des dépenses d'un comité d'organisation et le budget de ses commissions. Le comité de suivi est
 * actif d'office pour un Magal ; le Jewrine national peut l'activer pour un autre comité. Son Jewrine est proposé par
 * le président et validé par le Jewrine national, ou désigné par le super administrateur. Il arbitre le budget des
 * commissions (retenir, réduire, augmenter, répartir) dans l'enveloppe de l'événement, en regardant l'argent réellement
 * encaissé ; sans comité de suivi, c'est le Jewrine national. Chaque révision est tracée.
 */
final class SuiviDepensesService
{
    public function __construct(private readonly NominationService $nominations) {}

    /** Le Jewrine national active ou désactive le comité de suivi des dépenses d'un comité d'organisation. */
    public function activer(Instance $comite, bool $actif, User $auteur): void
    {
        if (! $comite->estComiteOrganisation() || ! $auteur->can('comites.diriger')) {
            throw new DomainException('Le Jewrine national active le comité de suivi des dépenses d\'un comité d\'organisation.');
        }

        $comite->update(['suivi_depenses' => $actif]);
    }

    /** Le président (ou ceux qui gèrent le comité) propose le Jewrine du suivi des dépenses. */
    public function peutProposer(User $utilisateur, Instance $comite): bool
    {
        return $comite->estComiteOrganisation() && $comite->suivi_depenses && ComiteService::peutGerer($utilisateur, $comite);
    }

    public function proposer(Instance $comite, Membre $membre, User $auteur): PropositionSuiviDepenses
    {
        if (! $this->peutProposer($auteur, $comite)) {
            throw new DomainException('Le président du comité propose le Jewrine du suivi des dépenses (comité de suivi actif).');
        }

        if ($membre->user === null || ! $membre->user->actif) {
            throw new DomainException('Proposez un membre validé qui a son compte : il signera depuis le panneau.');
        }

        if ($comite->propositionsSuivi()->where('statut', PropositionSuiviDepenses::PROPOSEE)->exists()) {
            throw new DomainException('Une proposition attend déjà la décision du Jewrine national.');
        }

        $proposition = PropositionSuiviDepenses::query()->create(['instance_id' => $comite->id, 'membre_id' => $membre->id, 'propose_par' => $auteur->id]);

        foreach ($this->nationaux() as $national) {
            if ($national->id !== $auteur->id) {
                $national->notify(new NotificationMembre(
                    'Suivi des dépenses : proposition à valider',
                    $auteur->name.' propose '.$membre->nom_complet.' comme Jewrine du suivi des dépenses de « '.$comite->nom.' ».',
                    route('filament.admin.resources.instances.view', $comite),
                    'Valider ou refuser',
                ));
            }
        }

        return $proposition;
    }

    public function peutDecider(User $utilisateur, PropositionSuiviDepenses $proposition): bool
    {
        return $proposition->statut === PropositionSuiviDepenses::PROPOSEE && $utilisateur->can('nominations.gerer') && $proposition->propose_par !== $utilisateur->id;
    }

    /** Le Jewrine national valide : la nomination suit (elle remplace la précédente). */
    public function valider(PropositionSuiviDepenses $proposition, User $auteur): Nomination
    {
        if (! $this->peutDecider($auteur, $proposition)) {
            throw new DomainException('Le Jewrine national valide la proposition du président (jamais la sienne).');
        }

        return DB::transaction(function () use ($proposition, $auteur): Nomination {
            $proposition->update(['statut' => PropositionSuiviDepenses::VALIDEE, 'decide_par' => $auteur->id, 'decide_le' => now()]);

            return $this->nommer($proposition->instance, $proposition->membre, $auteur);
        });
    }

    public function refuser(PropositionSuiviDepenses $proposition, string $motif, User $auteur): void
    {
        if (! $this->peutDecider($auteur, $proposition)) {
            throw new DomainException('Le Jewrine national refuse la proposition du président.');
        }

        if (trim($motif) === '') {
            throw new DomainException('Indiquez le motif du refus.');
        }

        $proposition->update(['statut' => PropositionSuiviDepenses::REFUSEE, 'decide_par' => $auteur->id, 'decide_le' => now(), 'motif' => mb_substr(trim($motif), 0, 255)]);
        $proposition->proposant->notify(new NotificationMembre(
            'Suivi des dépenses : proposition refusée',
            $proposition->membre->nom_complet.' n\'est pas retenu : '.trim($motif).'.',
            route('filament.admin.resources.instances.view', $proposition->instance),
            'Proposer quelqu\'un d\'autre',
        ));
    }

    /** Le super administrateur désigne directement le Jewrine du suivi des dépenses. */
    public function designer(Instance $comite, Membre $membre, User $auteur): Nomination
    {
        if (! CasquettesService::estSuperAdministrateur($auteur)) {
            throw new DomainException('Seul le super administrateur désigne directement le Jewrine du suivi des dépenses.');
        }

        if (! $comite->estComiteOrganisation() || ! $comite->suivi_depenses) {
            throw new DomainException('Activez d\'abord le comité de suivi des dépenses de ce comité.');
        }

        return $this->nommer($comite, $membre, $auteur);
    }

    private function nommer(Instance $comite, Membre $membre, User $auteur): Nomination
    {
        $poste = Poste::query()->where('code', Poste::JEWRINE_SUIVI_DEPENSES)->firstOrFail();
        $compte = $membre->user ?? throw new DomainException('Ce membre n\'a pas de compte.');
        $nomination = $this->nominations->nommer($compte, $poste, null, $auteur, comite: $comite);

        $compte->notify(new NotificationMembre(
            'Jewrine du suivi des dépenses : '.$comite->nom,
            'Vous arbitrez le budget des commissions et signez les ordres de dépense de « '.$comite->nom.' ».',
            route('filament.admin.resources.instances.view', $comite),
            'Ouvrir l\'espace',
        ));

        return $nomination;
    }

    /** Qui arbitre le budget : le Jewrine du suivi des dépenses de ce comité s'il est actif ; sinon le Jewrine national. */
    public function peutArbitrer(User $utilisateur, Instance $comite): bool
    {
        if (! $comite->estComiteOrganisation() || ! $utilisateur->can('comites.arbitrer')) {
            return false;
        }

        if ($comite->suivi_depenses) {
            return $comite->jewrineSuiviDepenses()?->id === $utilisateur->id || CasquettesService::estSuperAdministrateur($utilisateur);
        }

        return $utilisateur->can('depenses.signer');
    }

    /** L'arbitre fixe le budget d'une commission (retenir, réduire, augmenter), dans l'enveloppe ; tracé, le chef prévenu. */
    public function arbitrer(CommissionComite $commission, int $budget, string $motif, User $auteur): RevisionBudget
    {
        $comite = $commission->instance;

        if (! $this->peutArbitrer($auteur, $comite)) {
            throw new DomainException($comite->suivi_depenses ? 'Le Jewrine du suivi des dépenses arbitre le budget des commissions.' : 'Le Jewrine national arbitre le budget des commissions.');
        }

        if ($budget < 0) {
            throw new DomainException('Le budget est un montant positif.');
        }

        if (trim($motif) === '') {
            throw new DomainException('Le motif de la révision est obligatoire.');
        }

        $enveloppe = $comite->collecte?->budget;
        $autres = (int) CommissionComite::query()->where('instance_id', $comite->id)->whereNull('archive_le')->whereKeyNot($commission->id)->get()
            ->sum(fn (CommissionComite $autre): int => $autre->budget_arrete ?? 0);

        if ($enveloppe !== null && $autres + $budget > $enveloppe) {
            throw new DomainException('L\'enveloppe de '.CollecteService::fcfa($enveloppe).' est dépassée : il reste '.CollecteService::fcfa(max(0, $enveloppe - $autres)).' à répartir.');
        }

        $revision = DB::transaction(function () use ($commission, $budget, $motif, $auteur): RevisionBudget {
            $revision = RevisionBudget::query()->create([
                'commission_comite_id' => $commission->id, 'ancien' => $commission->budget_arrete, 'nouveau' => $budget,
                'motif' => mb_substr(trim($motif), 0, 255), 'auteur_id' => $auteur->id,
            ]);
            $commission->forceFill(['budget_arrete' => $budget])->save();

            return $revision;
        });

        $chef = $commission->chef?->user;

        if ($chef !== null && $chef->id !== $auteur->id) {
            $chef->notify(new NotificationMembre(
                'Budget de la commission '.$commission->nom,
                ($revision->ancien !== null ? 'Votre budget passe de '.CollecteService::fcfa($revision->ancien).' à ' : 'Votre budget est arrêté à ').CollecteService::fcfa($budget).' : '.trim($motif).'.',
                route('comites.index'),
                'Mes comités',
            ));
        }

        return $revision;
    }

    /** Répartir : une part du budget d'une commission passe à une autre (deux révisions tracées). */
    public function repartir(CommissionComite $depuis, CommissionComite $vers, int $montant, string $motif, User $auteur): void
    {
        if ($depuis->instance_id !== $vers->instance_id || $depuis->is($vers)) {
            throw new DomainException('Choisissez deux commissions différentes du même comité.');
        }

        if ($montant < 1 || $montant > $depuis->budget()) {
            throw new DomainException('Le montant va de 1 à '.CollecteService::fcfa($depuis->budget()).' (le budget de la commission '.$depuis->nom.').');
        }

        DB::transaction(function () use ($depuis, $vers, $montant, $motif, $auteur): void {
            $this->arbitrer($depuis, $depuis->budget() - $montant, 'Vers la commission '.$vers->nom.' : '.trim($motif), $auteur);
            $this->arbitrer($vers, $vers->budget() + $montant, 'Depuis la commission '.$depuis->nom.' : '.trim($motif), $auteur);
        });
    }

    /**
     * Les indicateurs du tableau de bord : promis, encaissé, en vérification, décaissé, disponible réel, enveloppe et
     * budget réparti, tâches.
     *
     * @return array{promis: int, encaisse: int, verification: int, decaisse: int, disponible: int, enveloppe: ?int, reparti: int, a_repartir: ?int, taches: int, taches_faites: int}
     */
    public function indicateurs(Instance $comite): array
    {
        $collecte = $comite->collecte;
        $encaisse = $collecte?->totalVerse() ?? 0;
        $decaisse = $collecte !== null ? OrdreDepense::decaisseNet(OrdreDepense::query()->where('collecte_id', $collecte->id)) : 0;
        $reparti = (int) $comite->commissions()->get()->sum(fn (CommissionComite $commission): int => $commission->budget_arrete ?? 0);
        $avancement = app(ComiteService::class)->avancement($comite);

        return [
            'promis' => $collecte?->totalEngage() ?? 0,
            'encaisse' => $encaisse,
            'verification' => $collecte !== null ? (int) $collecte->paiements()->where('statut', StatutPaiement::Declare->value)->sum('montant') : 0,
            'decaisse' => $decaisse,
            'disponible' => $encaisse - $decaisse,
            'enveloppe' => $collecte?->budget,
            'reparti' => $reparti,
            'a_repartir' => $collecte?->budget !== null ? $collecte->budget - $reparti : null,
            'taches' => $avancement['actions'],
            'taches_faites' => $avancement['faites'],
        ];
    }

    /** @return iterable<User> */
    private function nationaux(): iterable
    {
        return User::query()->whereIn('id', Nomination::query()->whereHas('poste', fn ($query) => $query->where('code', Poste::JEWRINE_NATIONAL))->select('user_id'))->get();
    }
}
