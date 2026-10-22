<?php

namespace App\Services;

use App\Models\Caisse;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\Commission;
use App\Models\CommissionComite;
use App\Models\JustificatifDepense;
use App\Models\LibelleDepense;
use App\Models\MouvementCaisse;
use App\Models\Nomination;
use App\Models\OrdreDepense;
use App\Models\Poste;
use App\Models\ProgrammeCommission;
use App\Models\Projet;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Support\Numerotation;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Les ordres de dépense : aucun argent ne sort sans eux. Un responsable demande (la Finance pour toute la Daara ; un chef
 * de commission, un responsable de projet, un Jewrine de cellule ou de commission permanente pour son périmètre), le
 * Jewrine national signe, la Finance exécute depuis une caisse avec la preuve. Personne ne signe son propre ordre, et
 * celui qui ordonne (le signataire) n'est pas celui qui paie. Une caisse ne passe jamais en négatif.
 */
final class OrdreDepenseService
{
    public function __construct(private readonly CasquettesService $casquettes) {}

    /**
     * Ce à quoi cette personne peut imputer une dépense.
     *
     * @return array{tout: bool, commissions_comite: list<int>, projets: list<int>, cellules: list<int>, commissions: list<int>}
     */
    public function perimetre(User $utilisateur): array
    {
        $membreId = $utilisateur->membre->id ?? 0;

        return [
            'tout' => $utilisateur->can('depenses.demander'),
            'commissions_comite' => array_values(array_map('intval', CommissionComite::query()->where('chef_id', $membreId)
                ->whereHas('instance', fn ($query) => $query->where('statut', 'active'))->pluck('id')->all())),
            'projets' => array_values(array_map('intval', Projet::query()->where('responsable_id', $membreId)->pluck('id')->all())),
            'cellules' => $this->casquettes->cellulesDirigees($utilisateur),
            'commissions' => array_values(array_map('intval', Commission::query()->dirigeesPar($utilisateur)->pluck('id')->all())),
        ];
    }

    public function peutDemander(User $utilisateur): bool
    {
        $perimetre = $this->perimetre($utilisateur);

        return $perimetre['tout'] || $perimetre['commissions_comite'] !== [] || $perimetre['projets'] !== [] || $perimetre['cellules'] !== [] || $perimetre['commissions'] !== [];
    }

    /**
     * Demande un ordre de dépense.
     *
     * Les champs (objet, libelle_depense_id, montant, beneficiaire, rattachement et l'imputation) sont vérifiés ici.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function demander(array $donnees, ?UploadedFile $devis, User $auteur): OrdreDepense
    {
        $donnees = $this->verifierImputation($donnees, $auteur);

        if ($donnees['montant'] < 1) {
            throw new DomainException('Indiquez le montant de la dépense.');
        }

        if (! array_key_exists($donnees['nature'], OrdreDepense::NATURES)) {
            throw new DomainException('Choisissez la nature de l\'ordre : paiement direct, avance ou remboursement.');
        }

        if ($donnees['nature'] !== OrdreDepense::PAIEMENT && trim($donnees['beneficiaire']) === '') {
            $donnees['beneficiaire'] = $auteur->name;
        }

        if ($donnees['nature'] === OrdreDepense::REMBOURSEMENT && $devis === null) {
            throw new DomainException('Pour un remboursement, joignez le reçu de votre achat.');
        }

        if (trim($donnees['objet']) === '' || trim($donnees['beneficiaire']) === '') {
            throw new DomainException('Indiquez l\'objet de la dépense et son bénéficiaire.');
        }

        if (! LibelleDepense::query()->enActivite()->whereKey($donnees['libelle_depense_id'])->exists()) {
            throw new DomainException('Choisissez un libellé de dépense en activité.');
        }

        $chemin = $devis?->store('depenses', 'local');

        /** @var OrdreDepense $ordre */
        $ordre = Numerotation::creer(OrdreDepense::class, 'numero', 'OD-'.now()->format('Y').'-', fn (string $numero): OrdreDepense => OrdreDepense::query()->create([
            ...$donnees,
            'numero' => $numero,
            'objet' => mb_substr(trim($donnees['objet']), 0, 255),
            'beneficiaire' => mb_substr(trim($donnees['beneficiaire']), 0, 150),
            'devis_path' => is_string($chemin) ? $chemin : null,
            'demande_par' => $auteur->id,
        ]));

        foreach ($this->signataires($ordre) as $signataire) {
            if ($signataire->id !== $auteur->id) {
                $signataire->notify(new NotificationMembre(
                    'Ordre de dépense à signer : '.$ordre->numero,
                    $auteur->name.' demande '.CollecteService::fcfa($ordre->montant).' pour « '.$ordre->objet.' » ('.$ordre->imputation().').',
                    route('filament.admin.resources.ordres-depense.index'),
                    'Signer ou refuser',
                ));
            }
        }

        return $ordre;
    }

    /**
     * L'imputation demandée doit être dans le périmètre du demandeur ; les champs inutiles sont vidés.
     *
     * @param  array<string, mixed>  $donnees
     * @return array{objet: string, libelle_depense_id: int, montant: int, beneficiaire: string, rattachement: string, nature: string, collecte_id: ?int, commission_comite_id: ?int, projet_id: ?int, cellule_id: ?int, commission_id: ?int}
     */
    private function verifierImputation(array $donnees, User $auteur): array
    {
        $perimetre = $this->perimetre($auteur);
        $id = fn (string $cle): ?int => filled($donnees[$cle] ?? null) ? (int) $donnees[$cle] : null;
        $imputation = ['collecte_id' => null, 'commission_comite_id' => null, 'projet_id' => null, 'cellule_id' => null, 'commission_id' => null];
        $permis = $perimetre['tout'];

        switch ($donnees['rattachement'] ?? null) {
            case 'programme':
                $commissionComite = $id('commission_comite_id') !== null ? CommissionComite::query()->with('instance')->whereKey($id('commission_comite_id'))->first() : null;
                $imputation['commission_comite_id'] = $commissionComite?->id;
                $imputation['collecte_id'] = $id('collecte_id') ?? $commissionComite?->instance->collecte_id;
                $permis = $permis || ($commissionComite !== null && in_array($commissionComite->id, $perimetre['commissions_comite'], true));

                // Une commission permanente choisie pour l'événement : son Jewrine demande pour elle.
                if ($id('commission_id') !== null) {
                    $choisie = $imputation['collecte_id'] !== null && ProgrammeCommission::query()->where('collecte_id', $imputation['collecte_id'])
                        ->where('commission_id', $id('commission_id'))->whereNull('retiree_le')->exists();

                    if (! $choisie) {
                        throw new DomainException('Cette commission n\'est pas choisie pour organiser ce programme.');
                    }

                    $imputation['commission_id'] = $id('commission_id');
                    $permis = $permis || in_array($imputation['commission_id'], $perimetre['commissions'], true);
                }

                if ($imputation['collecte_id'] === null && $commissionComite === null) {
                    throw new DomainException('Choisissez le programme (ou la commission de son comité).');
                }
                break;
            case 'projet':
                $imputation['projet_id'] = $id('projet_id') ?? throw new DomainException('Choisissez le projet.');
                $permis = $permis || in_array($imputation['projet_id'], $perimetre['projets'], true);
                break;
            case 'fonctionnement':
                $imputation['cellule_id'] = $id('cellule_id');
                $imputation['commission_id'] = $id('commission_id');
                $permis = $permis
                    || ($imputation['cellule_id'] !== null && in_array($imputation['cellule_id'], $perimetre['cellules'], true))
                    || ($imputation['commission_id'] !== null && in_array($imputation['commission_id'], $perimetre['commissions'], true));
                break;
            default:
                throw new DomainException('Indiquez à quoi la dépense est rattachée : un programme, un projet ou le fonctionnement.');
        }

        if (! $permis) {
            throw new DomainException('Vous demandez des dépenses seulement pour votre périmètre (votre commission, votre projet, votre cellule).');
        }

        return [
            'objet' => (string) ($donnees['objet'] ?? ''), 'libelle_depense_id' => (int) ($donnees['libelle_depense_id'] ?? 0),
            'montant' => (int) ($donnees['montant'] ?? 0), 'beneficiaire' => (string) ($donnees['beneficiaire'] ?? ''),
            'rattachement' => (string) $donnees['rattachement'], 'nature' => (string) ($donnees['nature'] ?? OrdreDepense::PAIEMENT), ...$imputation,
        ];
    }

    public function annuler(OrdreDepense $ordre, User $auteur): void
    {
        if ($ordre->statut !== OrdreDepense::DEMANDE || $ordre->demande_par !== $auteur->id) {
            throw new DomainException('Seul le demandeur annule son ordre, tant qu\'il n\'est pas signé.');
        }

        $ordre->update(['statut' => OrdreDepense::ANNULE]);
        app(CorrectionPaiementService::class)->apresOrdre($ordre);
    }

    /**
     * Le comité d'organisation de l'ordre, si son comité de suivi des dépenses est actif et a son Jewrine : c'est lui
     * qui signe. Sans Jewrine nommé (ou sans comité de suivi), le Jewrine national signe.
     */
    public function jewrineSignataire(OrdreDepense $ordre): ?User
    {
        $comite = $ordre->commissionComite->instance ?? $ordre->collecte?->comite;

        return $comite !== null && $comite->estComiteOrganisation() && $comite->suivi_depenses ? $comite->jewrineSuiviDepenses() : null;
    }

    /** Signer : le Jewrine du suivi des dépenses pour son comité, sinon le Jewrine national ; jamais son propre ordre. */
    public function peutSigner(User $utilisateur, OrdreDepense $ordre): bool
    {
        if ($ordre->statut !== OrdreDepense::DEMANDE || $ordre->demande_par === $utilisateur->id) {
            return false;
        }

        $jewrine = $this->jewrineSignataire($ordre);

        return $jewrine !== null
            ? ($jewrine->id === $utilisateur->id || $this->suppleantSignataire($ordre)?->id === $utilisateur->id) && $utilisateur->can('depenses.signer_comite')
            : $utilisateur->can('depenses.signer');
    }

    /** Le suppléant du Jewrine du suivi des dépenses de l'ordre, s'il y en a un. */
    public function suppleantSignataire(OrdreDepense $ordre): ?User
    {
        $comite = $ordre->commissionComite->instance ?? $ordre->collecte?->comite;

        return $this->jewrineSignataire($ordre) !== null ? $comite?->suppleantSuiviDepenses() : null;
    }

    public function signer(OrdreDepense $ordre, User $auteur): void
    {
        if (! $this->peutSigner($auteur, $ordre)) {
            throw new DomainException($this->jewrineSignataire($ordre) !== null
                ? 'Le Jewrine du comité de suivi des dépenses signe l\'ordre (jamais le sien).'
                : 'Le Jewrine national signe l\'ordre (jamais le sien).');
        }

        $parSuppleant = $this->suppleantSignataire($ordre)?->id === $auteur->id;
        $ordre->update(['statut' => OrdreDepense::SIGNE, 'signe_le' => now(), 'signe_par' => $auteur->id, 'signe_par_suppleant' => $parSuppleant]);

        $ordre->demandeur->notify(new NotificationMembre('Ordre signé : '.$ordre->numero, '« '.$ordre->objet.' » est signé'.($parSuppleant ? ' par le suppléant' : '').' ; la Finance va l\'exécuter.', route('filament.admin.resources.ordres-depense.index'), 'Voir'));

        foreach (User::permission('depenses.executer')->get() as $tresorier) {
            if ($tresorier->id !== $ordre->demande_par && $tresorier->id !== $auteur->id && ! $tresorier->hasRole('super_administrateur')) {
                $tresorier->notify(new NotificationMembre('Ordre à exécuter : '.$ordre->numero, CollecteService::fcfa($ordre->montant).' pour « '.$ordre->objet.' » ('.$ordre->beneficiaire.').', route('filament.admin.resources.ordres-depense.index'), 'Exécuter'));
            }
        }
    }

    public function refuser(OrdreDepense $ordre, string $motif, User $auteur): void
    {
        if (! $this->peutSigner($auteur, $ordre)) {
            throw new DomainException('Celui qui signe les ordres de ce périmètre refuse l\'ordre (jamais le sien).');
        }

        if (trim($motif) === '') {
            throw new DomainException('Indiquez le motif du refus.');
        }

        $ordre->update(['statut' => OrdreDepense::REFUSE, 'signe_le' => now(), 'signe_par' => $auteur->id, 'signe_par_suppleant' => $this->suppleantSignataire($ordre)?->id === $auteur->id,
            'motif_refus' => mb_substr(trim($motif), 0, 255)]);
        app(CorrectionPaiementService::class)->apresOrdre($ordre);
        $ordre->demandeur->notify(new NotificationMembre('Ordre refusé : '.$ordre->numero, '« '.$ordre->objet.' » n\'est pas signé : '.trim($motif).'.', route('filament.admin.resources.ordres-depense.index'), 'Voir'));
    }

    /**
     * Exécuter : la Finance, jamais celui qui a signé l'ordre (celui qui ordonne n'est pas celui qui paie). Le Jewrine
     * finance paie aussi un ordre qu'il a lui-même demandé, puisque le Jewrine national l'a signé entre-temps.
     */
    public function peutExecuter(User $utilisateur, OrdreDepense $ordre): bool
    {
        return $ordre->statut === OrdreDepense::SIGNE && $utilisateur->can('depenses.executer') && $ordre->signe_par !== $utilisateur->id;
    }

    /** L'argent sort de la caisse choisie, avec la preuve (reçu, facture) ; la caisse ne passe jamais en négatif. */
    public function executer(OrdreDepense $ordre, Caisse $caisse, UploadedFile $preuve, User $auteur): void
    {
        if (! $this->peutExecuter($auteur, $ordre)) {
            throw new DomainException('La Finance exécute l\'ordre signé (jamais celui qui l\'a signé).');
        }

        if ($caisse->estArchive()) {
            throw new DomainException('Cette caisse est archivée.');
        }

        $chemin = $preuve->store('depenses', 'local');

        if ($chemin === false) {
            throw new RuntimeException('Impossible d\'enregistrer la preuve.');
        }

        DB::transaction(function () use ($ordre, $caisse, $chemin, $auteur): void {
            $caisse = Caisse::query()->whereKey($caisse->id)->lockForUpdate()->firstOrFail();

            if ($caisse->solde() < $ordre->montant) {
                throw new DomainException('Solde insuffisant dans « '.$caisse->nom.' » : '.CollecteService::fcfa($caisse->solde()).' pour '.CollecteService::fcfa($ordre->montant).'.');
            }

            MouvementCaisse::query()->create([
                'caisse_id' => $caisse->id, 'sens' => MouvementCaisse::SORTIE, 'montant' => $ordre->montant, 'nature' => 'depense',
                'libelle' => $ordre->numero.' · '.$ordre->libelle->nom.' · '.$ordre->objet.' · '.$ordre->beneficiaire,
                'ordre_depense_id' => $ordre->id, 'auteur_id' => $auteur->id, 'date_operation' => now(),
            ]);

            $ordre->update(['statut' => OrdreDepense::EXECUTE, 'execute_le' => now(), 'execute_par' => $auteur->id, 'caisse_id' => $caisse->id, 'preuve_path' => $chemin]);
        });

        app(CorrectionPaiementService::class)->apresOrdre($ordre->fresh() ?? $ordre);
        $this->alerterSiDepassement($ordre->fresh() ?? $ordre);
        $ordre->demandeur->notify(new NotificationMembre('Ordre exécuté : '.$ordre->numero, CollecteService::fcfa($ordre->montant).' versés pour « '.$ordre->objet.' » ('.$ordre->beneficiaire.').', route('filament.admin.resources.ordres-depense.index'), 'Voir'));
    }

    /**
     * Les signataires d'un ordre : le Jewrine du suivi des dépenses de son comité, sinon les Jewrine nationaux nommés.
     *
     * @return Collection<int, User>
     */
    private function signataires(OrdreDepense $ordre): Collection
    {
        $jewrine = $this->jewrineSignataire($ordre);

        return $jewrine !== null
            ? new Collection(array_filter([$jewrine, $this->suppleantSignataire($ordre)]))
            : User::query()->whereIn('id', Nomination::query()->whereHas('poste', fn ($query) => $query->where('code', Poste::JEWRINE_NATIONAL))->select('user_id'))->get();
    }

    /** Une commission qui dépasse son budget : le président du comité et celui qui arbitre sont prévenus. */
    private function alerterSiDepassement(OrdreDepense $ordre): void
    {
        $commission = $ordre->commissionComite;

        if ($commission === null || $commission->budget() <= 0 || $commission->depense() <= $commission->budget()) {
            return;
        }

        $comite = $commission->instance;
        $destinataires = collect([$comite->president?->user, $this->jewrineSignataire($ordre)])->filter()->unique('id');

        foreach ($destinataires as $destinataire) {
            $destinataire->notify(new NotificationMembre(
                'Budget dépassé : commission '.$commission->nom,
                'Dépensé '.CollecteService::fcfa($commission->depense()).' pour un budget de '.CollecteService::fcfa($commission->budget()).' ('.$comite->nom.').',
                route('filament.admin.resources.instances.view', $comite),
                'Ouvrir l\'espace',
            ));
        }
    }

    /** Justifier une avance : celui qui l'a demandée, ou le chef de sa commission, tant qu'elle n'est pas soldée. */
    public function peutJustifier(User $utilisateur, OrdreDepense $ordre): bool
    {
        return $ordre->estAvance() && $ordre->statut === OrdreDepense::EXECUTE && $ordre->soldee_le === null
            && ($ordre->demande_par === $utilisateur->id || ($ordre->commissionComite !== null && ComiteService::dirige($utilisateur, $ordre->commissionComite)));
    }

    /** Le chef ajoute une pièce (achat, montant, photo du reçu) qui justifie son avance. */
    public function justifier(OrdreDepense $ordre, string $objet, int $montant, UploadedFile $piece, User $auteur): JustificatifDepense
    {
        if (! $this->peutJustifier($auteur, $ordre)) {
            throw new DomainException('Celui qui a reçu l\'avance la justifie, tant qu\'elle n\'est pas soldée.');
        }

        if (trim($objet) === '' || $montant < 1) {
            throw new DomainException('Indiquez l\'achat et son montant.');
        }

        if ($montant > $ordre->resteAJustifier()) {
            throw new DomainException('Il reste '.CollecteService::fcfa($ordre->resteAJustifier()).' à justifier sur cette avance.');
        }

        $chemin = $piece->store('depenses', 'local');

        if ($chemin === false) {
            throw new RuntimeException('Impossible d\'enregistrer la pièce.');
        }

        $justificatif = JustificatifDepense::query()->create([
            'ordre_depense_id' => $ordre->id, 'objet' => mb_substr(trim($objet), 0, 255), 'montant' => $montant, 'piece_path' => $chemin, 'ajoute_par' => $auteur->id,
        ]);
        $this->solderSiComplete($ordre->fresh() ?? $ordre);

        return $justificatif;
    }

    /** Enregistrer le reste rendu : la Finance, qui reçoit l'argent dans une caisse. */
    public function peutEnregistrerRetour(User $utilisateur, OrdreDepense $ordre): bool
    {
        return $ordre->estAvance() && $ordre->statut === OrdreDepense::EXECUTE && $ordre->soldee_le === null && $ordre->resteAJustifier() > 0
            && $utilisateur->can('depenses.executer');
    }

    /** Le chef rend ce qu'il n'a pas dépensé : l'argent rentre dans une caisse, et la dépense diminue d'autant. */
    public function enregistrerRetour(OrdreDepense $ordre, int $montant, Caisse $caisse, User $auteur): void
    {
        if (! $this->peutEnregistrerRetour($auteur, $ordre)) {
            throw new DomainException('La Finance enregistre le reste rendu d\'une avance non soldée.');
        }

        if ($montant < 1 || $montant > $ordre->resteAJustifier()) {
            throw new DomainException('Le reste rendu va de 1 à '.CollecteService::fcfa($ordre->resteAJustifier()).'.');
        }

        if ($caisse->estArchive()) {
            throw new DomainException('Cette caisse est archivée.');
        }

        DB::transaction(function () use ($ordre, $montant, $caisse, $auteur): void {
            MouvementCaisse::query()->create([
                'caisse_id' => $caisse->id, 'sens' => MouvementCaisse::ENTREE, 'montant' => $montant, 'nature' => 'retour',
                'libelle' => $ordre->numero.' · reste de l\'avance rendu', 'ordre_depense_id' => $ordre->id, 'auteur_id' => $auteur->id, 'date_operation' => now(),
            ]);
            $ordre->increment('montant_rendu', $montant);
        });

        $this->solderSiComplete($ordre->fresh() ?? $ordre);
    }

    /** L'avance est soldée quand tout ce qui a été décaissé est justifié. */
    private function solderSiComplete(OrdreDepense $ordre): void
    {
        if ($ordre->soldee_le !== null || $ordre->resteAJustifier() > 0) {
            return;
        }

        $ordre->update(['soldee_le' => now()]);
        $ordre->demandeur->notify(new NotificationMembre(
            'Avance soldée : '.$ordre->numero,
            '« '.$ordre->objet.' » : justifiée '.CollecteService::fcfa($ordre->justifie()).($ordre->montant_rendu > 0 ? ', rendue '.CollecteService::fcfa($ordre->montant_rendu) : '').'.',
            route('comites.index'),
            'Mes comités',
        ));
    }

    /**
     * Les choix d'imputation proposés à cette personne, pour les formulaires.
     *
     * @return array{collectes: array<int, string>, commissions_comite: array<int, string>, projets: array<int, string>, cellules: array<int, string>, commissions: array<int, string>}
     */
    public function choix(User $utilisateur): array
    {
        $perimetre = $this->perimetre($utilisateur);
        $tout = $perimetre['tout'];

        return [
            'collectes' => Collecte::query()
                ->when(! $tout, fn ($query) => $query->whereIn('id', ProgrammeCommission::query()->whereIn('commission_id', $perimetre['commissions'])->whereNull('retiree_le')->select('collecte_id')))
                ->latest('ouverture')->pluck('titre', 'id')->all(),
            'commissions_comite' => CommissionComite::query()->with('instance')->when(! $tout, fn ($query) => $query->whereKey($perimetre['commissions_comite']))
                ->whereHas('instance', fn ($query) => $query->where('statut', 'active'))->get()
                ->mapWithKeys(fn (CommissionComite $commission): array => [$commission->id => $commission->instance->nom.' · '.$commission->nom])->all(),
            'projets' => Projet::query()->when(! $tout, fn ($query) => $query->whereKey($perimetre['projets']))->pluck('titre', 'id')->all(),
            'cellules' => Cellule::query()->enActivite()->when(! $tout, fn ($query) => $query->whereKey($perimetre['cellules']))->orderBy('nom')->pluck('nom', 'id')->all(),
            'commissions' => Commission::query()->when(! $tout, fn ($query) => $query->whereKey($perimetre['commissions']))->orderBy('nom')->pluck('nom', 'id')->all(),
        ];
    }
}
