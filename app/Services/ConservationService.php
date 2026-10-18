<?php

namespace App\Services;

use App\Enums\StatutCollecte;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Cellule;
use App\Models\CellulePointeur;
use App\Models\Collecte;
use App\Models\CommissionComite;
use App\Models\Competence;
use App\Models\Evenement;
use App\Models\Indicateur;
use App\Models\Jalon;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\ParticipationSympathisant;
use App\Models\RepresentantFinance;
use App\Models\Reunion;
use App\Models\Sympathisant;
use App\Models\User;
use App\Notifications\NotificationMembre;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * « Rien ne se perd » : ce qui n'a plus cours est annulé, archivé ou clos, jamais effacé.
 * Chaque opération garde sa date, son auteur et, pour une annulation, son motif.
 */
final class ConservationService
{
    /** Un programme sans versement s'annule ; un programme qui a reçu de l'argent se clôture. */
    public function annulerProgramme(Collecte $collecte, string $motif, User $auteur): void
    {
        if ($collecte->statut === StatutCollecte::Annulee) {
            throw new DomainException('Ce programme est déjà annulé.');
        }

        $aRecuDeLArgent = Paiement::query()
            ->where('collecte_id', $collecte->id)
            ->where('statut', '!=', StatutPaiement::Refuse->value)
            ->exists()
            || ParticipationSympathisant::query()->where('collecte_id', $collecte->id)->exists();

        if ($aRecuDeLArgent) {
            throw new DomainException('Ce programme a déjà reçu des versements : clôturez-le au lieu de l\'annuler.');
        }

        $collecte->forceFill([
            'statut' => StatutCollecte::Annulee,
            'annule_le' => now(),
            'annule_par' => $auteur->id,
            'motif_annulation' => $this->motif($motif),
        ])->save();
    }

    /** Une activité ou une dahira qui n'a pas eu lieu. Les membres déjà prévenus le jour même reçoivent l'annulation. */
    public function annulerEvenement(Evenement $evenement, string $motif, User $auteur): void
    {
        if ($evenement->estAnnule()) {
            throw new DomainException('Cette activité est déjà annulée.');
        }

        if ($evenement->presences()->exists()) {
            throw new DomainException('Des présences sont déjà pointées : cette activité a eu lieu, elle ne peut plus être annulée.');
        }

        $evenement->forceFill([
            'annule_le' => now(),
            'annule_par' => $auteur->id,
            'motif_annulation' => $this->motif($motif),
        ])->save();

        if ($evenement->estDahira() && $evenement->avec_rappel && $evenement->debut->isToday()) {
            $this->prevenirDeLAnnulation($evenement);
        }
    }

    /** Une réunion qui n'a pas eu lieu. Si elle était convoquée, les membres du comité sont prévenus. */
    public function annulerReunion(Reunion $reunion, string $motif, User $auteur): void
    {
        if ($reunion->estAnnule()) {
            throw new DomainException('Cette réunion est déjà annulée.');
        }

        if ($reunion->estPubliee()) {
            throw new DomainException('Le procès-verbal de cette réunion est publié : elle ne peut plus être annulée.');
        }

        $etaitConvoquee = $reunion->convoquee_le !== null;

        $reunion->forceFill([
            'statut' => 'annulee',
            'annule_le' => now(),
            'annule_par' => $auteur->id,
            'motif_annulation' => $this->motif($motif),
        ])->save();

        if ($etaitConvoquee) {
            foreach (app(ComiteService::class)->comptesDesMembres($reunion->instance) as $compte) {
                $compte->notify(new NotificationMembre(
                    'Réunion annulée : '.$reunion->instance->nom,
                    $reunion->titre.' du '.$reunion->debut->translatedFormat('l j F à H\hi').' n\'aura pas lieu. Motif : '.$reunion->motif_annulation,
                    route('comites.index'),
                    'Voir mes comités',
                ));
            }
        }
    }

    /** Archive une cellule, une commission de comité, une étape ou un indicateur de projet. */
    public function archiver(Cellule|CommissionComite|Jalon|Indicateur|Competence $objet, User $auteur): void
    {
        if ($objet->estArchive()) {
            throw new DomainException('Déjà archivé.');
        }

        if ($objet instanceof Cellule) {
            $this->verifierCelluleVide($objet);
        }

        if ($objet instanceof CommissionComite) {
            $this->verifierCommissionVide($objet);
        }

        $objet->forceFill(['archive_le' => now(), 'archive_par' => $auteur->id])->save();
    }

    public function desarchiver(Cellule|CommissionComite|Jalon|Indicateur|Competence $objet): void
    {
        $objet->forceFill(['archive_le' => null, 'archive_par' => null])->save();
    }

    /** Met fin à une désignation de pointeur ou de représentant finance ; l'historique reste. */
    public function mettreFin(CellulePointeur|RepresentantFinance $designation, User $auteur): void
    {
        if ($designation->estTerminee()) {
            throw new DomainException('Cette désignation a déjà pris fin.');
        }

        DB::transaction(function () use ($designation, $auteur): void {
            $designation->forceFill(['fin_le' => now(), 'fin_par' => $auteur->id])->save();

            if ($designation instanceof RepresentantFinance) {
                app(CasquettesService::class)->synchroniser($designation->user);
            }
        });
    }

    /**
     * Retire un sympathisant du registre : son nom et son téléphone sont effacés,
     * ses visites et ses participations (avec leurs reçus) restent, sans le nommer.
     */
    public function anonymiserSympathisant(Sympathisant $sympathisant): void
    {
        if ($sympathisant->anonymise_le !== null) {
            return;
        }

        $sympathisant->forceFill([
            'prenom' => 'Sympathisant',
            'nom' => 'anonyme',
            'telephone' => null,
            'invite_par_id' => null,
            'anonymise_le' => now(),
        ])->save();

        JournalActivite::enregistrer($sympathisant, 'anonymise');
    }

    private function verifierCelluleVide(Cellule $cellule): void
    {
        if ($cellule->membres()->exists()) {
            throw new DomainException('Cette cellule a encore des membres : changez-les de cellule avant de l\'archiver.');
        }

        if (CellulePointeur::query()->where('cellule_id', $cellule->id)->exists()
            || RepresentantFinance::query()->where('cellule_id', $cellule->id)->exists()) {
            throw new DomainException('Mettez d\'abord fin aux pointeurs et aux représentants finance de cette cellule.');
        }
    }

    private function verifierCommissionVide(CommissionComite $commission): void
    {
        $occupee = $commission->chef_id !== null
            || $commission->membres()->exists()
            || $commission->besoins()->exists()
            || $commission->depenses()->exists()
            || $commission->actions()->exists();

        if ($occupee) {
            throw new DomainException('Seule une commission vide (sans chef, membres, besoins, dépenses ni tâches) peut être archivée.');
        }
    }

    private function prevenirDeLAnnulation(Evenement $evenement): void
    {
        $comptes = User::query()->whereIn('id', Membre::query()
            ->where('statut', StatutMembre::Valide->value)
            ->when($evenement->cellule_id !== null, fn ($query) => $query->where('cellule_id', $evenement->cellule_id))
            ->whereNotNull('user_id')
            ->select('user_id'))->get();

        foreach ($comptes as $compte) {
            $compte->notify(new NotificationMembre(
                'Dahira annulée',
                'La dahira de '.$evenement->debut->format('H\hi').' ('.$evenement->lieu.') n\'aura pas lieu aujourd\'hui. Motif : '.$evenement->motif_annulation,
            ));
        }
    }

    private function motif(string $motif): string
    {
        $motif = trim($motif);

        if ($motif === '') {
            throw new DomainException('Indiquez le motif.');
        }

        return mb_substr($motif, 0, 255);
    }
}
