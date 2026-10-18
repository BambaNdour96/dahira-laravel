<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\CellulePointeur;
use App\Models\ChangementCellule;
use App\Models\Membre;
use App\Models\Nomination;
use App\Models\Poste;
use App\Models\RepresentantFinance;
use App\Models\User;
use App\Notifications\NotificationMembre;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Le changement de cellule : demandé par le membre ou son Jewrine, validé par le super administrateur seul. À la
 * validation, les proches dont il gère la fiche le suivent, ses postes dans l'ancienne cellule prennent fin, et
 * l'historique des cellules est tenu. Les versements passés gardent la cellule du moment où ils ont été faits.
 */
final class ChangementCelluleService
{
    public function __construct(
        private readonly CasquettesService $casquettes,
        private readonly NominationService $nominations,
        private readonly ConservationService $conservation,
    ) {}

    /** Le membre lui-même, ou le Jewrine de sa cellule (et ceux qui modifient toutes les fiches). */
    public function peutDemander(User $utilisateur, Membre $membre): bool
    {
        return ! $membre->estGereParUnProche()
            && ($membre->user_id === $utilisateur->id || $this->casquettes->peut($utilisateur, 'membres.modifier', $membre->cellule_id));
    }

    public function demander(Membre $membre, Cellule $arrivee, ?string $motif, User $auteur): ChangementCellule
    {
        if ($membre->estGereParUnProche()) {
            throw new DomainException('Ce proche suit la cellule du membre qui gère sa fiche.');
        }

        if (! $this->peutDemander($auteur, $membre)) {
            throw new DomainException('Le changement de cellule est demandé par le membre ou par son Jewrine de cellule.');
        }

        if (in_array($membre->statut, [StatutMembre::Rejete, StatutMembre::Radie], true)) {
            throw new DomainException('Ce membre ne peut plus changer de cellule.');
        }

        if ($arrivee->id === $membre->cellule_id || $arrivee->estArchive()) {
            throw new DomainException('Choisissez une autre cellule, en activité.');
        }

        if ($membre->changementsCellule()->where('statut', ChangementCellule::EN_ATTENTE)->exists()) {
            throw new DomainException('Une demande de changement de cellule attend déjà la validation du super administrateur.');
        }

        $changement = ChangementCellule::query()->create([
            'membre_id' => $membre->id,
            'cellule_depart_id' => $membre->cellule_id,
            'cellule_arrivee_id' => $arrivee->id,
            'motif' => filled($motif) ? mb_substr(trim((string) $motif), 0, 255) : null,
            'demande_par' => $auteur->id,
        ]);

        foreach (User::role('super_administrateur')->get() as $super) {
            $super->notify(new NotificationMembre(
                'Changement de cellule à valider',
                $membre->nom_complet.' : '.$membre->cellule->nom.' → '.$arrivee->nom.'.',
                route('filament.admin.resources.changements-cellule.index'),
                'Examiner la demande',
            ));
        }

        if ($membre->user !== null && $membre->user_id !== $auteur->id) {
            $membre->user->notify(new NotificationMembre(
                'Changement de cellule demandé',
                'Votre Jewrine a demandé votre passage à la cellule '.$arrivee->nom.'. Le super administrateur décidera.',
                route('cellule.index'),
                'Voir la demande',
            ));
        }

        return $changement;
    }

    public function retirer(ChangementCellule $changement, User $auteur): void
    {
        if ($changement->statut !== ChangementCellule::EN_ATTENTE || ($changement->demande_par !== $auteur->id && $changement->membre->user_id !== $auteur->id)) {
            throw new DomainException('Seule une demande en attente se retire, par le membre ou celui qui l\'a faite.');
        }

        $changement->update(['statut' => ChangementCellule::RETIRE, 'decide_le' => now(), 'decide_par' => $auteur->id]);
    }

    /**
     * Ce que la validation entraînera, à montrer avant de valider : les proches qui suivent, les postes de l'ancienne
     * cellule qui prendront fin, et s'il est le Jewrine de cette cellule.
     *
     * @return array{proches: list<string>, postes: list<string>, estJewrine: bool}
     */
    public function consequences(ChangementCellule $changement): array
    {
        $membre = $changement->membre;
        $postes = [];
        $estJewrine = false;

        if ($membre->user_id !== null) {
            foreach (Nomination::query()->with('poste')->where('user_id', $membre->user_id)->where('cellule_id', $changement->cellule_depart_id)->get() as $nomination) {
                $postes[] = $nomination->poste->nom;
                $estJewrine = $estJewrine || $nomination->poste->code === Poste::JEWRINE_CELLULE;
            }

            foreach (CellulePointeur::query()->where('user_id', $membre->user_id)->where('cellule_id', $changement->cellule_depart_id)->get() as $pointeur) {
                $postes[] = $pointeur->perimetre === 'femmes' ? 'Pointeuse' : 'Pointeur';
            }

            if (RepresentantFinance::query()->where('user_id', $membre->user_id)->where('cellule_id', $changement->cellule_depart_id)->exists()) {
                $postes[] = 'Représentant finance';
            }
        }

        return [
            'proches' => array_values($membre->proches()->pluck('prenom')->map(fn (mixed $prenom): string => (string) $prenom)->all()),
            'postes' => array_values(array_unique($postes)),
            'estJewrine' => $estJewrine,
        ];
    }

    /** Le texte de l'avertissement montré au super administrateur avant de valider. */
    public function avertissement(ChangementCellule $changement): string
    {
        $consequences = $this->consequences($changement);
        $lignes = [];

        if ($consequences['estJewrine']) {
            $lignes[] = 'ATTENTION : '.$changement->membre->nom_complet.' est le Jewrine de la cellule '.$changement->depart->nom
                .'. Sa nomination prendra fin : la cellule n\'aura plus de Jewrine, et le Jewrine national sera prévenu qu\'il faut nommer un remplaçant.';
        }

        if ($consequences['postes'] !== []) {
            $lignes[] = 'Ses postes dans la cellule '.$changement->depart->nom.' prendront fin : '.implode(', ', $consequences['postes']).'.';
        }

        if ($consequences['proches'] !== []) {
            $lignes[] = 'Les proches dont il gère la fiche le suivent : '.implode(', ', $consequences['proches']).'.';
        }

        $lignes[] = 'Les dahiras, les présences et les versements passés restent comptés pour la cellule '.$changement->depart->nom.'.';

        return implode("\n\n", $lignes);
    }

    public function valider(ChangementCellule $changement, User $auteur): void
    {
        $this->verifierDecision($changement, $auteur);
        $consequences = $this->consequences($changement);
        $membre = $changement->membre;
        $ancienJewrine = $changement->depart->jewrine();

        DB::transaction(function () use ($changement, $auteur, $membre): void {
            foreach ([$membre, ...$membre->proches()->get()->all()] as $personne) {
                $personne->changementEnCours = $changement->id;
                $personne->update(['cellule_id' => $changement->cellule_arrivee_id]);
            }

            if ($membre->user_id !== null) {
                foreach (Nomination::query()->with('poste')->where('user_id', $membre->user_id)->where('cellule_id', $changement->cellule_depart_id)->get() as $nomination) {
                    $this->nominations->mettreFin($nomination, 'Changement de cellule : '.$changement->arrivee->nom, $auteur);
                }

                foreach (CellulePointeur::query()->where('user_id', $membre->user_id)->where('cellule_id', $changement->cellule_depart_id)->get() as $pointeur) {
                    $this->conservation->mettreFin($pointeur, $auteur);
                }

                foreach (RepresentantFinance::query()->where('user_id', $membre->user_id)->where('cellule_id', $changement->cellule_depart_id)->get() as $representant) {
                    $this->conservation->mettreFin($representant, $auteur);
                }
            }

            $changement->update(['statut' => ChangementCellule::VALIDE, 'decide_le' => now(), 'decide_par' => $auteur->id]);
        });

        $this->prevenirApresValidation($changement, $consequences, $ancienJewrine, $auteur);
    }

    public function refuser(ChangementCellule $changement, string $motif, User $auteur): void
    {
        $this->verifierDecision($changement, $auteur);

        if (trim($motif) === '') {
            throw new DomainException('Indiquez le motif du refus.');
        }

        $changement->update(['statut' => ChangementCellule::REFUSE, 'decide_le' => now(), 'decide_par' => $auteur->id, 'motif_refus' => mb_substr(trim($motif), 0, 255)]);

        $message = 'Le passage de '.$changement->membre->nom_complet.' à la cellule '.$changement->arrivee->nom.' n\'est pas validé : '.trim($motif).'.';

        foreach (collect([$changement->membre->user, $changement->demandeur])->filter()->unique('id') as $compte) {
            $compte->notify(new NotificationMembre('Changement de cellule refusé', $message, route('cellule.index'), 'Voir'));
        }
    }

    private function verifierDecision(ChangementCellule $changement, User $auteur): void
    {
        if (! $auteur->can('membres.changer_cellule')) {
            throw new DomainException('Seul le super administrateur valide un changement de cellule.');
        }

        if ($changement->statut !== ChangementCellule::EN_ATTENTE) {
            throw new DomainException('Cette demande a déjà été traitée.');
        }
    }

    /**
     * @param  array{proches: list<string>, postes: list<string>, estJewrine: bool}  $consequences
     */
    private function prevenirApresValidation(ChangementCellule $changement, array $consequences, ?User $ancienJewrine, User $auteur): void
    {
        $membre = $changement->membre;
        $trajet = $changement->depart->nom.' → '.$changement->arrivee->nom;

        $membre->user?->notify(new NotificationMembre(
            'Nouvelle cellule : '.$changement->arrivee->nom,
            'Votre passage à la cellule '.$changement->arrivee->nom.' est validé'.($consequences['proches'] !== [] ? ', avec '.implode(', ', $consequences['proches']) : '').'. Votre historique vous suit.',
            route('espace'),
            'Ouvrir mon compte',
        ));

        $nouveauJewrine = $changement->arrivee->jewrine();

        foreach (array_filter([$ancienJewrine, $nouveauJewrine]) as $jewrine) {
            if ($jewrine->id === $membre->user_id) {
                continue;
            }

            $jewrine->notify(new NotificationMembre(
                'Changement de cellule : '.$membre->nom_complet,
                $membre->nom_complet.' change de cellule ('.$trajet.')'.($consequences['proches'] !== [] ? ', avec '.implode(', ', $consequences['proches']) : '').'.',
                route('filament.admin.resources.membres.edit', $membre),
                'Ouvrir la fiche',
            ));
        }

        if (! $consequences['estJewrine']) {
            return;
        }

        foreach (User::query()->whereIn('id', Nomination::query()->whereHas('poste', fn ($query) => $query->where('code', Poste::JEWRINE_NATIONAL))->select('user_id'))->get() as $national) {
            if ($national->id === $auteur->id) {
                continue;
            }

            $national->notify(new NotificationMembre(
                'Cellule '.$changement->depart->nom.' sans Jewrine',
                $membre->nom_complet.', Jewrine de la cellule '.$changement->depart->nom.', passe à la cellule '.$changement->arrivee->nom.'. Il faut nommer un remplaçant.',
                route('filament.admin.resources.nominations.index'),
                'Nommer un Jewrine',
            ));
        }
    }
}
