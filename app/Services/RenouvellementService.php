<?php

namespace App\Services;

use App\Enums\MoyenPaiement;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\CampagneRenouvellement;
use App\Models\Cellule;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Renouvellement;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Observers\AvisMembreObserver;
use App\Policies\MembrePolicy;
use App\Support\AntiFraude;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Le renouvellement annuel. Le super administrateur ouvre la campagne d'un exercice ; chaque membre vérifie sa fiche et
 * paie les frais de renouvellement (le montant des frais de carte). Si les informations de sa carte n'ont pas changé, le
 * renouvellement est automatique ; sinon, le Jewrine de cellule revalide. À la date limite, les cartes non renouvelées
 * sont révoquées et le membre devient « non renouvelé », sans perte de données ; il est réactivé dès qu'il paie.
 */
final class RenouvellementService
{
    /** Les membres validés dans les 3 mois avant l'ouverture sont renouvelés d'office : leurs frais viennent d'être payés. */
    public const MOIS_D_OFFICE = 3;

    /** Motif de révocation de l'ancienne carte, remplacée par celle de l'exercice renouvelé. */
    public const MOTIF_CARTE = 'Renouvellement ';

    /** Les relances avant la date limite, en jours (une relance part aussi à l'ouverture). */
    public const RELANCES = [30, 7, 1];

    public function __construct(
        private readonly CarteService $cartes,
        private readonly StatutMembreService $statuts,
        private readonly CasquettesService $casquettes,
    ) {}

    /** L'empreinte des informations de la carte : si elle change, le Jewrine revalide. */
    public static function empreinte(Membre $membre): string
    {
        return hash('sha256', implode('|', [
            $membre->prenom, $membre->nom, $membre->date_naissance?->format('Y-m-d') ?? '', $membre->photo_path ?? '', $membre->sexe->value,
        ]));
    }

    public function ouvrir(Exercice $exercice, Carbon $dateLimite, User $auteur): CampagneRenouvellement
    {
        if (! $auteur->can('renouvellement.ouvrir')) {
            throw new DomainException('Seul le super administrateur ouvre la campagne de renouvellement.');
        }

        if (CampagneRenouvellement::ouverte() !== null) {
            throw new DomainException('Une campagne de renouvellement est déjà en cours.');
        }

        if (CampagneRenouvellement::query()->where('exercice_id', $exercice->id)->exists()) {
            throw new DomainException('La campagne de l\'exercice '.$exercice->libelle.' a déjà été ouverte.');
        }

        if (! $dateLimite->isFuture()) {
            throw new DomainException('La date limite doit être à venir.');
        }

        $campagne = DB::transaction(function () use ($exercice, $dateLimite, $auteur): CampagneRenouvellement {
            $campagne = CampagneRenouvellement::query()->create([
                'exercice_id' => $exercice->id, 'date_limite' => $dateLimite->toDateString(), 'ouverte_le' => now(), 'ouverte_par' => $auteur->id,
            ]);
            $recents = now()->subMonths(self::MOIS_D_OFFICE);

            foreach (Membre::query()->whereIn('statut', [StatutMembre::Valide->value, StatutMembre::NonRenouvele->value])->with('carteActive')->cursor() as $membre) {
                $dOffice = $membre->statut === StatutMembre::Valide && $membre->valide_le !== null && $membre->valide_le->gte($recents);
                $this->inscrire($campagne, $membre, $dOffice);
            }

            return $campagne;
        });

        $this->relancer($campagne, 'La campagne de renouvellement '.$exercice->libelle.' est ouverte');

        return $campagne;
    }

    /** Un membre validé pendant la campagne est renouvelé d'office pour cet exercice. */
    public function apresValidation(Membre $membre): void
    {
        $campagne = CampagneRenouvellement::ouverte();

        if ($campagne !== null && ! $campagne->renouvellements()->where('membre_id', $membre->id)->exists()) {
            $this->inscrire($campagne, $membre->load('carteActive'), true);
        }
    }

    private function inscrire(CampagneRenouvellement $campagne, Membre $membre, bool $dOffice): void
    {
        Renouvellement::query()->create([
            'campagne_id' => $campagne->id,
            'membre_id' => $membre->id,
            'statut' => $dOffice ? Renouvellement::D_OFFICE : Renouvellement::A_FAIRE,
            'empreinte_fiche' => self::empreinte($membre),
            'renouvele_le' => $dOffice ? now() : null,
        ]);

        if ($dOffice) {
            $membre->carteActive?->update(['exercice_id' => $campagne->exercice_id]);
        }
    }

    /** Le renouvellement en cours du membre : celui de la dernière campagne. */
    public function pour(Membre $membre): ?Renouvellement
    {
        return Renouvellement::query()
            ->with('campagne.exercice')
            ->where('membre_id', $membre->id)
            ->latest('id')
            ->first();
    }

    /** Le membre lui-même, ou celui qui gère la fiche de son proche. */
    public static function peutRenouveler(User $utilisateur, Membre $membre): bool
    {
        return $membre->user_id === $utilisateur->id || MembrePolicy::gereLaFiche($utilisateur, $membre);
    }

    /** Étape 1 : le membre confirme que sa fiche est exacte. */
    public function verifierFiche(Renouvellement $renouvellement, User $auteur): void
    {
        $this->verifierAPayer($renouvellement, $auteur);
        $renouvellement->update(['fiche_verifiee_le' => now()]);
    }

    /** Une information de la carte est fausse : le Jewrine de cellule est prévenu, lui seul corrige l'identité. */
    public function signalerErreur(Renouvellement $renouvellement, string $message, User $auteur): void
    {
        $this->verifierAPayer($renouvellement, $auteur);

        if (trim($message) === '') {
            throw new DomainException('Indiquez ce qu\'il faut corriger.');
        }

        $membre = $renouvellement->membre;
        $membre->cellule->jewrine()?->notify(new NotificationMembre(
            'Fiche à corriger : '.$membre->nom_complet,
            'Pour son renouvellement, '.$auteur->name.' signale : « '.mb_substr(trim($message), 0, 300).' ».',
            route('filament.admin.resources.membres.edit', $membre),
            'Corriger la fiche',
        ));
    }

    /** Étape 2 : le paiement Wave des frais de renouvellement, déclaré par le membre ou pour lui. */
    public function declarer(Renouvellement $renouvellement, string $reference, ?string $numeroWave, User $declarant, MoyenPaiement $moyen = MoyenPaiement::Wave): Paiement
    {
        $this->verifierAPayer($renouvellement, $declarant);

        if ($renouvellement->fiche_verifiee_le === null) {
            throw new DomainException('Vérifiez d\'abord la fiche.');
        }

        return $this->creerDeclaration($renouvellement, $reference, $numeroWave, $declarant, $moyen);
    }

    /**
     * Un autre membre paie le renouvellement (« Payer pour un membre ») : le bénéficiaire, qui a son compte, est prévenu ;
     * la vérification de la fiche reste à sa main, et l'empreinte protège la carte s'il a changé.
     */
    public function declarerPourAutrui(Renouvellement $renouvellement, string $reference, ?string $numeroWave, User $payeur, MoyenPaiement $moyen = MoyenPaiement::Wave): Paiement
    {
        app(PaiementPourAutrui::class)->verifier($renouvellement->membre, $payeur);

        if (! in_array($renouvellement->statut, Renouvellement::A_PAYER, true)) {
            throw new DomainException('Ce renouvellement est déjà réglé.');
        }

        return $this->creerDeclaration($renouvellement, $reference, $numeroWave, $payeur, $moyen);
    }

    private function creerDeclaration(Renouvellement $renouvellement, string $reference, ?string $numeroWave, User $declarant, MoyenPaiement $moyen): Paiement
    {
        if ($this->paiementEnCours($renouvellement->membre) !== null) {
            throw new DomainException('Un paiement de renouvellement est déjà en cours de vérification.');
        }

        $paiement = $renouvellement->membre->paiements()->create([
            'objet' => 'renouvellement',
            'montant' => app(PaiementService::class)->montantFraisCarte(),
            'nombre_mois' => 0,
            'moyen' => $moyen,
            'reference' => $reference,
            'numero_wave' => $numeroWave,
            'statut' => StatutPaiement::Declare,
            'declare_le' => now(),
            'declare_par' => $declarant->id,
        ]);

        RepresentantFinanceService::prevenir($paiement);

        return $paiement;
    }

    public function paiementEnCours(Membre $membre): ?Paiement
    {
        return $membre->paiements()->where('objet', 'renouvellement')->where('statut', StatutPaiement::Declare->value)->latest('declare_le')->first();
    }

    /** La Finance encaisse en espèces les frais de renouvellement. */
    public function enregistrerEspeces(Membre $membre, ?User $tresorier): Paiement
    {
        $renouvellement = $this->pour($membre);

        if ($renouvellement === null || ! in_array($renouvellement->statut, Renouvellement::A_PAYER, true)) {
            throw new DomainException('Ce membre n\'a pas de renouvellement à payer.');
        }

        AntiFraude::verifierValidateur($membre, $tresorier);

        return DB::transaction(function () use ($membre, $tresorier): Paiement {
            $paiement = $membre->paiements()->create([
                'objet' => 'renouvellement',
                'montant' => app(PaiementService::class)->montantFraisCarte(),
                'nombre_mois' => 0,
                'moyen' => MoyenPaiement::Especes,
                'statut' => StatutPaiement::Confirme,
                'declare_le' => now(),
                'traite_le' => now(),
                'traite_par' => $tresorier?->id,
            ]);

            $this->apresPaiement($paiement, $tresorier);

            return $paiement;
        });
    }

    /** Le paiement est validé : renouvellement automatique, ou revalidation par le Jewrine si la carte change. */
    public function apresPaiement(Paiement $paiement, ?User $tresorier): void
    {
        $renouvellement = $this->pour($paiement->membre);

        if ($renouvellement === null || ! in_array($renouvellement->statut, Renouvellement::A_PAYER, true)) {
            return;
        }

        $renouvellement->update(['paiement_id' => $paiement->id]);
        $membre = $paiement->membre;

        if ($renouvellement->empreinte_fiche === null || $renouvellement->empreinte_fiche === self::empreinte($membre)) {
            $this->renouveler($renouvellement, $tresorier);

            return;
        }

        $renouvellement->update(['statut' => Renouvellement::A_REVALIDER]);
        $membre->cellule->jewrine()?->notify(new NotificationMembre(
            'Renouvellement à revalider : '.$membre->nom_complet,
            'Les informations de sa carte ont changé depuis l\'ouverture de la campagne : vérifiez sa fiche, puis revalidez son renouvellement.',
            route('filament.admin.resources.campagnes-renouvellement.view', $renouvellement->campagne),
            'Revalider',
        ));
        AvisMembreObserver::avertir($membre, 'Renouvellement payé', 'Votre fiche a changé : votre Jewrine de cellule la revalide, puis votre nouvelle carte sera émise.', route('espace'), 'Ouvrir mon compte');
    }

    /** Le Jewrine de cellule revalide une fiche qui a changé (jamais celle d'un proche dont il gère la fiche). */
    public function revalider(Renouvellement $renouvellement, User $jewrine): void
    {
        if ($renouvellement->statut !== Renouvellement::A_REVALIDER) {
            throw new DomainException('Ce renouvellement n\'attend pas de revalidation.');
        }

        $membre = $renouvellement->membre;

        if (! $this->casquettes->peut($jewrine, 'membres.valider', $membre->cellule_id) || $membre->user_id === $jewrine->id || MembrePolicy::gereLaFiche($jewrine, $membre)) {
            throw new DomainException('Le Jewrine de la cellule revalide ce renouvellement (jamais le sien ni celui d\'un proche dont il gère la fiche).');
        }

        $this->renouveler($renouvellement, $jewrine);
    }

    private function renouveler(Renouvellement $renouvellement, ?User $auteur): void
    {
        $campagne = $renouvellement->campagne;
        $membre = $renouvellement->membre;

        DB::transaction(function () use ($renouvellement, $campagne, $membre, $auteur): void {
            $active = $membre->carteActive()->first();

            if ($membre->statut === StatutMembre::NonRenouvele) {
                // La réactivation émet la nouvelle carte : elle est de l'exercice renouvelé.
                $this->statuts->changer($membre, StatutMembre::Valide, $auteur);
                $membre->carteActive()->first()?->update(['exercice_id' => $campagne->exercice_id]);
            } elseif ($active !== null) {
                $this->cartes->revoquer($active, $auteur, self::MOTIF_CARTE.$campagne->exercice->libelle, $campagne->exercice);
            } else {
                $this->cartes->emettre($membre, $campagne->exercice);
            }

            $renouvellement->update([
                'statut' => Renouvellement::RENOUVELE,
                'renouvele_le' => now(),
                'revalide_par' => $renouvellement->statut === Renouvellement::A_REVALIDER ? $auteur?->id : null,
            ]);
        });

        AvisMembreObserver::avertir($membre, 'Adhésion '.$campagne->exercice->libelle.' renouvelée', 'Barakallahou fikoum. Votre nouvelle carte de membre '.$campagne->exercice->libelle.' est disponible.', route('carte'), 'Voir ma carte');
    }

    /**
     * Chaque matin : les relances 30, 7 et 1 jour avant la date limite ; après la date limite, la clôture.
     *
     * @return array{relances: int, non_renouveles: int}
     */
    public function routine(): array
    {
        $campagne = CampagneRenouvellement::ouverte();

        if ($campagne === null) {
            return ['relances' => 0, 'non_renouveles' => 0];
        }

        if (now()->startOfDay()->gt($campagne->date_limite)) {
            return ['relances' => 0, 'non_renouveles' => $this->cloturer($campagne)];
        }

        $jours = (int) now()->startOfDay()->diffInDays($campagne->date_limite->copy()->startOfDay());
        $relances = 0;

        foreach (self::RELANCES as $avant) {
            $colonne = 'relance_'.$avant.'_le';

            if ($jours <= $avant && $campagne->getAttribute($colonne) === null) {
                $relances += $this->relancer($campagne, $avant === 1 ? 'Dernier jour demain pour renouveler votre adhésion' : 'Plus que '.$jours.' jours pour renouveler votre adhésion');
                $campagne->forceFill([$colonne => now()])->save();

                break;
            }
        }

        return ['relances' => $relances, 'non_renouveles' => 0];
    }

    private function relancer(CampagneRenouvellement $campagne, string $titre): int
    {
        $nombre = 0;

        foreach ($campagne->renouvellements()->where('statut', Renouvellement::A_FAIRE)->with(['membre.user', 'membre.responsable.user'])->cursor() as $renouvellement) {
            AvisMembreObserver::avertir(
                $renouvellement->membre,
                $titre,
                'Renouvellement '.$campagne->exercice->libelle.' : vérifiez la fiche et réglez les frais ('.CollecteService::fcfa(app(PaiementService::class)->montantFraisCarte()).') avant le '.$campagne->date_limite->translatedFormat('j F Y').'.',
                route('renouvellement.show'),
                'Renouveler',
            );
            $nombre++;
        }

        return $nombre;
    }

    /** À la date limite : les cartes non renouvelées sont révoquées, sans perte de données ; chaque Jewrine reçoit le bilan de sa cellule. */
    public function cloturer(CampagneRenouvellement $campagne): int
    {
        $parCellule = [];

        DB::transaction(function () use ($campagne, &$parCellule): void {
            foreach ($campagne->renouvellements()->where('statut', Renouvellement::A_FAIRE)->with(['membre.cellule', 'membre.carteActive'])->get() as $renouvellement) {
                $membre = $renouvellement->membre;
                $renouvellement->update(['statut' => Renouvellement::NON_RENOUVELE]);

                if ($membre->carteActive !== null) {
                    $this->cartes->desactiver($membre->carteActive, null, AvisMembreObserver::NON_RENOUVELEE);
                }

                if ($membre->statut === StatutMembre::Valide) {
                    $this->statuts->changer($membre, StatutMembre::NonRenouvele, null);
                }

                $parCellule[$membre->cellule_id][] = $membre->nom_complet;
            }

            $campagne->forceFill(['cloturee_le' => now()])->save();
        });

        foreach ($parCellule as $celluleId => $noms) {
            Cellule::query()->whereKey($celluleId)->first()?->jewrine()?->notify(new NotificationMembre(
                'Renouvellement '.$campagne->exercice->libelle.' : '.count($noms).' non renouvelé(s)',
                implode(', ', $noms).'. Ils retrouvent leur carte dès qu\'ils paient.',
                route('filament.admin.resources.campagnes-renouvellement.view', $campagne),
                'Voir la campagne',
            ));
        }

        return array_sum(array_map('count', $parCellule));
    }

    private function verifierAPayer(Renouvellement $renouvellement, User $auteur): void
    {
        if (! self::peutRenouveler($auteur, $renouvellement->membre)) {
            throw new DomainException('Seuls le membre et celui qui gère sa fiche renouvellent son adhésion.');
        }

        if (! in_array($renouvellement->statut, Renouvellement::A_PAYER, true)) {
            throw new DomainException('Ce renouvellement est déjà réglé.');
        }
    }
}
