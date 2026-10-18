<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\ActionComite;
use App\Models\Commission;
use App\Models\CommissionComite;
use App\Models\Instance;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\PointOrdreDuJour;
use App\Models\PresenceReunion;
use App\Models\Reunion;
use App\Models\User;
use App\Notifications\NotificationMembre;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Les comités de la Daara : le Comité directeur (tenu par le super administrateur), les CDGMT et autres comités,
 * leurs commissions, leurs réunions et leurs PV.
 */
final class ComiteService
{
    /** Créer un comité d'organisation et en désigner le président (« comites.diriger » : super administrateur, Jewrine national). */
    public static function peutCreer(User $utilisateur): bool
    {
        return $utilisateur->can('comites.diriger');
    }

    /**
     * Gérer un comité. Le Comité directeur : « comite_directeur.composer ». L'espace d'une commission permanente :
     * son Jewrine, ou « commissions.gerer ». Un comité d'organisation : « comites.diriger », ou son président.
     */
    public static function peutGerer(User $utilisateur, Instance $instance): bool
    {
        if ($instance->estComiteDirecteur()) {
            return $utilisateur->can('comite_directeur.composer');
        }

        if ($instance->estCommission()) {
            return $utilisateur->can('commissions.gerer')
                || ($instance->commission !== null && Commission::query()->dirigeesPar($utilisateur)->whereKey($instance->commission->id)->exists());
        }

        return $utilisateur->can('comites.diriger')
            || ($instance->president !== null && $instance->president->user_id === $utilisateur->id);
    }

    /** Organiser les réunions (préparer, convoquer) : comme gérer, plus « comite_directeur.convoquer » pour le Comité directeur. */
    public static function peutOrganiser(User $utilisateur, Instance $instance): bool
    {
        return self::peutGerer($utilisateur, $instance)
            || ($instance->estComiteDirecteur() && $utilisateur->can('comite_directeur.convoquer'));
    }

    /**
     * Rédiger la réunion. Avant la convocation, l'organisateur la prépare (ordre du jour, secrétaire de séance).
     * Dès la convocation, seul le secrétaire de séance anime, enregistre les présences, rédige et publie le PV ;
     * le super administrateur reste le secours.
     */
    public static function peutRediger(User $utilisateur, Reunion $reunion): bool
    {
        if ($reunion->estPubliee() || $reunion->estAnnule()) {
            return false;
        }

        $estSecretaire = $reunion->secretaire !== null && $reunion->secretaire->user_id === $utilisateur->id;

        if ($reunion->convoquee_le === null) {
            return $estSecretaire || self::peutOrganiser($utilisateur, $reunion->instance);
        }

        return $estSecretaire || $utilisateur->can('plateforme.administrer');
    }

    /**
     * Modifier une réunion déjà convoquée (date, lieu, lien, ordre du jour) : l'organisateur, jusqu'au début
     * de la réunion. Les présences, la rédaction et le PV restent au seul secrétaire de séance.
     */
    public static function peutModifierConvocation(User $utilisateur, Reunion $reunion): bool
    {
        return $reunion->convoquee_le !== null
            && ! $reunion->estPubliee()
            && ! $reunion->estAnnule()
            && $reunion->debut->isFuture()
            && self::peutOrganiser($utilisateur, $reunion->instance);
    }

    /**
     * Les secrétaires de séance possibles : les membres de la commission pour l'espace d'une commission
     * permanente ; les membres du Comité directeur pour le Comité directeur et les comités d'organisation.
     *
     * @return array<int, string>
     */
    public static function secretairesPossibles(Instance $instance): array
    {
        return self::choixMembres($instance->estCommission() ? $instance : Instance::comiteDirecteur());
    }

    /** Lire un comité, ses PV et ses décisions : ses membres et le Comité directeur. */
    public static function peutLire(User $utilisateur, Instance $instance): bool
    {
        if ($utilisateur->can('comites.consulter')) {
            return true;
        }

        $membre = $utilisateur->membre;

        return $membre !== null && $instance->membres()->whereKey($membre->id)->exists();
    }

    /**
     * Les membres du Comité directeur.
     *
     * @param  list<int>  $membres
     */
    public function definirComiteDirecteur(array $membres, User $auteur): void
    {
        if (! $auteur->can('comite_directeur.composer')) {
            throw new DomainException('Seul le super administrateur tient la liste du Comité directeur.');
        }

        $comite = Instance::comiteDirecteur();
        $changements = $comite->membres()->sync($membres);
        $this->synchroniserRoles();

        foreach (['attached' => true, 'detached' => false] as $cle => $ajoute) {
            foreach (Membre::query()->whereKey($changements[$cle])->get() as $membre) {
                JournalActivite::appartenance($comite, 'Comité directeur', $membre, $ajoute);
            }
        }
    }

    public function ajouterAuComiteDirecteur(Membre $membre, User $auteur): void
    {
        $actuels = Instance::comiteDirecteur()->membres()->pluck('membres.id')->map(fn (mixed $id): int => (int) $id)->all();
        $this->definirComiteDirecteur(array_values(array_unique([...$actuels, $membre->id])), $auteur);
    }

    public function retirerDuComiteDirecteur(Membre $membre, User $auteur): void
    {
        $actuels = Instance::comiteDirecteur()->membres()->pluck('membres.id')->map(fn (mixed $id): int => (int) $id)->all();
        $this->definirComiteDirecteur(array_values(array_diff($actuels, [$membre->id])), $auteur);
    }

    /** La casquette « comite_directeur » suit exactement la liste du Comité directeur. */
    public function synchroniserRoles(): void
    {
        $comptes = Instance::comiteDirecteur()->membres()->whereNotNull('user_id')->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
        $casquettes = app(CasquettesService::class);

        foreach (User::role('comite_directeur')->whereNotIn('id', $comptes)->get() as $utilisateur) {
            $casquettes->synchroniser($utilisateur);
        }

        foreach (User::query()->whereIn('id', $comptes)->get() as $utilisateur) {
            $casquettes->synchroniser($utilisateur);
        }
    }

    /** Un nouveau CDGMT commence avec tous les membres du Comité directeur. */
    public function preparerCdgmt(Instance $instance): void
    {
        if ($instance->type !== Instance::CDGMT) {
            return;
        }

        $instance->membres()->syncWithoutDetaching(Instance::comiteDirecteur()->membres()->pluck('membres.id')->all());
    }

    /** Le président, les chefs et les membres des commissions font toujours partie du comité. */
    public function integrerResponsables(Instance $instance): void
    {
        $ids = $instance->commissions()->whereNotNull('chef_id')->pluck('chef_id')->all();

        foreach ($instance->commissions()->with('membres')->get() as $commission) {
            array_push($ids, ...$commission->membres->modelKeys());
        }

        if ($instance->president_id !== null) {
            $ids[] = $instance->president_id;
        }

        $instance->membres()->syncWithoutDetaching(array_values(array_unique($ids)));
    }

    /** Recopie les commissions du comité précédent du même type, sans les chefs ; ses besoins retenus reviennent en propositions. */
    public function reprendreCommissions(Instance $instance): int
    {
        $precedent = Instance::query()
            ->where('type', $instance->type)
            ->where('id', '!=', $instance->id)
            ->latest('annee')
            ->latest('id')
            ->first();

        if ($precedent === null) {
            throw new DomainException('Aucun comité précédent de ce type.');
        }

        $nombre = 0;

        foreach ($precedent->commissions as $commission) {
            $nouvelle = CommissionComite::query()->firstOrCreate(
                ['instance_id' => $instance->id, 'nom' => $commission->nom],
                ['mission' => $commission->mission, 'ordre' => $commission->ordre],
            );

            foreach ($commission->besoins()->where('statut', 'retenu')->get() as $besoin) {
                $nouvelle->besoins()->create([
                    'designation' => $besoin->designation,
                    'quantite' => $besoin->quantite,
                    'prix_unitaire' => $besoin->prix_unitaire,
                ]);
            }

            $nombre++;
        }

        return $nombre;
    }

    /**
     * Les membres validés de la Daara, pour les listes de choix.
     *
     * @return array<int, string>
     */
    public static function choixMembres(?Instance $seulement = null): array
    {
        $requete = $seulement !== null ? $seulement->membres() : Membre::query();

        return $requete->where('membres.statut', StatutMembre::Valide->value)
            ->orderBy('membres.nom')
            ->get()
            ->mapWithKeys(fn (Membre $membre): array => [$membre->id => $membre->prenom.' '.$membre->nom.' — '.$membre->matricule])
            ->all();
    }

    /** Convoque la réunion : prévient les membres et renvoie le message pour le groupe WhatsApp. */
    public function convoquer(Reunion $reunion): string
    {
        if ($reunion->estAnnule()) {
            throw new DomainException('Cette réunion est annulée.');
        }

        $reunion->update(['statut' => $reunion->estPubliee() ? 'publiee' : 'convoquee', 'convoquee_le' => now()]);
        $message = $this->messageConvocation($reunion);

        foreach ($this->comptesDesMembres($reunion->instance) as $compte) {
            $compte->notify(new NotificationMembre(
                'Convocation : '.$reunion->instance->nom,
                $reunion->titre.' — '.$reunion->debut->translatedFormat('l j F à H\hi').', '.$reunion->lieu.'.',
                route('comites.index'),
                'Voir l\'ordre du jour',
            ));
        }

        return $message;
    }

    /**
     * L'organisateur modifie la date, le lieu ou l'ordre du jour d'une réunion convoquée, jusqu'à son début.
     * Chaque modification envoie une « convocation modifiée » aux membres ; renvoie le message pour le groupe WhatsApp.
     *
     * @param  array{debut: string, lieu: string, lien?: string|null, points: list<array{id?: int|null, titre: string}>}  $donnees
     */
    public function modifierConvocation(Reunion $reunion, array $donnees, User $auteur): string
    {
        if (! self::peutModifierConvocation($auteur, $reunion)) {
            throw new DomainException('Seul l\'organisateur modifie une réunion convoquée, et seulement avant son début.');
        }

        $debut = Carbon::parse($donnees['debut']);

        if (! $debut->isFuture()) {
            throw new DomainException('La nouvelle date doit être à venir.');
        }

        $titres = array_values(array_filter(array_map(fn (array $point): string => trim($point['titre']), $donnees['points']), fn (string $titre): bool => $titre !== ''));

        if ($titres === []) {
            throw new DomainException('L\'ordre du jour compte au moins un point.');
        }

        $lien = trim((string) ($donnees['lien'] ?? '')) ?: null;
        $ordreAvant = $reunion->points->pluck('titre')->all();
        $changements = [];

        if (! $debut->equalTo($reunion->debut)) {
            $changements[] = 'nouvelle date : '.$debut->translatedFormat('l j F à H\hi');
        }

        if (trim($donnees['lieu']) !== $reunion->lieu || $lien !== $reunion->lien) {
            $changements[] = 'nouveau lieu : '.trim($donnees['lieu']).($lien !== null ? ' — '.$lien : '');
        }

        if ($titres !== $ordreAvant) {
            $changements[] = 'ordre du jour modifié';
        }

        if ($changements === []) {
            throw new DomainException('Rien n\'a changé : la convocation n\'est pas renvoyée.');
        }

        DB::transaction(function () use ($reunion, $donnees, $debut, $lien, $titres, $ordreAvant): void {
            $reunion->update(['debut' => $debut, 'lieu' => trim($donnees['lieu']), 'lien' => $lien]);
            $this->remplacerOrdreDuJour($reunion, $donnees['points']);

            if ($titres !== $ordreAvant) {
                JournalActivite::enregistrer($reunion, 'modifie', [
                    'avant' => ['ordre_du_jour' => implode(' ; ', $ordreAvant)],
                    'apres' => ['ordre_du_jour' => implode(' ; ', $titres)],
                ]);
            }
        });

        $reunion->refresh();

        foreach ($this->comptesDesMembres($reunion->instance) as $compte) {
            $compte->notify(new NotificationMembre(
                'Convocation modifiée : '.$reunion->instance->nom,
                $reunion->titre.' — '.ucfirst(implode(', ', $changements)).'.',
                route('comites.index'),
                'Voir l\'ordre du jour',
            ));
        }

        return $this->messageConvocation($reunion, modifiee: true);
    }

    /**
     * Met l'ordre du jour à jour : les points gardés le restent (avec leur résumé), les nouveaux s'ajoutent.
     * Un point retiré disparaît, sauf s'il a déjà un résumé : on ne perd jamais un compte rendu.
     *
     * @param  list<array{id?: int|null, titre: string}>  $points
     */
    private function remplacerOrdreDuJour(Reunion $reunion, array $points): void
    {
        $gardes = [];
        $ordre = 0;

        foreach ($points as $point) {
            $titre = trim($point['titre']);

            if ($titre === '') {
                continue;
            }

            $existant = isset($point['id']) ? $reunion->points()->whereKey($point['id'])->first() : null;
            $existant ??= new PointOrdreDuJour(['reunion_id' => $reunion->id]);
            $existant->fill(['titre' => $titre, 'ordre' => ++$ordre])->save();
            $gardes[] = $existant->id;
        }

        $retires = $reunion->points()->whereNotIn('id', $gardes);

        if ((clone $retires)->whereNotNull('resume')->where('resume', '!=', '')->exists()) {
            throw new DomainException('Un point retiré a déjà un résumé : gardez-le à l\'ordre du jour.');
        }

        $retires->delete();
    }

    public function messageConvocation(Reunion $reunion, bool $modifiee = false): string
    {
        $lignes = [
            '*'.$reunion->instance->nom.'*',
            ($modifiee ? '⚠️ Convocation modifiée : ' : 'Convocation : ').$reunion->titre,
            '📅 '.$reunion->debut->translatedFormat('l j F Y à H\hi'),
            '📍 '.$reunion->lieu.($reunion->lien ? ' — '.$reunion->lien : ''),
            '',
            '*Ordre du jour*',
        ];

        foreach ($reunion->points as $index => $point) {
            $lignes[] = ($index + 1).'. '.$point->titre;
        }

        return implode("\n", $lignes);
    }

    /**
     * Enregistre les présents et les excusés.
     *
     * @param  list<int>  $presents
     * @param  list<int>  $excuses
     */
    public function enregistrerPresences(Reunion $reunion, array $presents, array $excuses): void
    {
        DB::transaction(function () use ($reunion, $presents, $excuses): void {
            $avant = $reunion->presences()->where('statut', 'present')->count().' présent(s), '
                .$reunion->presences()->where('statut', 'excuse')->count().' excusé(s)';
            $reunion->presences()->delete();
            $excuses = array_diff(array_unique($excuses), $presents);

            JournalActivite::enregistrer($reunion, 'modifie', [
                'avant' => ['presences' => $avant],
                'apres' => ['presences' => count(array_unique($presents)).' présent(s), '.count($excuses).' excusé(s)'],
            ]);

            foreach (array_unique($presents) as $id) {
                PresenceReunion::query()->create(['reunion_id' => $reunion->id, 'membre_id' => $id, 'statut' => 'present']);
            }

            foreach ($excuses as $id) {
                PresenceReunion::query()->create(['reunion_id' => $reunion->id, 'membre_id' => $id, 'statut' => 'excuse']);
            }
        });
    }

    /**
     * Le quorum de la réunion, à titre d'information : les présents sur les membres, sans seuil.
     *
     * @return array{presents: int, membres: int}
     */
    public function quorum(Reunion $reunion): array
    {
        return [
            'presents' => $reunion->presences()->where('statut', 'present')->count(),
            'membres' => $reunion->instance->membres()->count(),
        ];
    }

    /** Publie le PV : il est figé, et les membres du comité sont prévenus. */
    public function publier(Reunion $reunion, User $auteur): void
    {
        if (! self::peutRediger($auteur, $reunion)) {
            throw new DomainException('Seul le secrétaire de séance publie le PV (le super administrateur en secours).');
        }

        $reunion->update(['statut' => 'publiee', 'publiee_le' => now(), 'publiee_par' => $auteur->id]);

        foreach ($this->comptesDesMembres($reunion->instance) as $compte) {
            if ($compte->id === $auteur->id) {
                continue;
            }

            $compte->notify(new NotificationMembre(
                'PV publié : '.$reunion->instance->nom,
                $reunion->titre.' du '.$reunion->debut->format('d/m/Y').'. Le PV est disponible.',
                route('reunions.pv', $reunion),
                'Lire le PV',
            ));
        }
    }

    public function lienWhatsapp(Reunion $reunion): string
    {
        $message = '*'.$reunion->instance->nom.'*'."\n"
            .'PV de la réunion du '.$reunion->debut->format('d/m/Y').' — '.$reunion->titre."\n"
            .$reunion->decisions()->count().' décision(s), '.$reunion->actions()->count().' action(s).'."\n"
            .'À lire ici : '.route('reunions.pv', $reunion);

        return 'https://wa.me/?text='.rawurlencode($message);
    }

    /** @return Collection<int, User> */
    public function comptesDesMembres(Instance $instance): Collection
    {
        return User::query()->whereIn('id', $instance->membres()->whereNotNull('user_id')->select('user_id'))->get();
    }

    /** Peut-on changer le statut de cette action ? L'organisateur, le responsable ou le chef de la commission. */
    public static function peutSuivre(User $utilisateur, ActionComite $action): bool
    {
        if (self::peutOrganiser($utilisateur, $action->instance)) {
            return true;
        }

        if ($action->commission !== null && self::dirige($utilisateur, $action->commission)) {
            return true;
        }

        return $action->compteResponsable()?->id === $utilisateur->id;
    }

    /** Le chef d'une commission (ou les organisateurs du comité) dirige sa commission : ses membres et ses tâches. */
    public static function dirige(User $utilisateur, CommissionComite $commission): bool
    {
        return ($commission->chef !== null && $commission->chef->user_id === $utilisateur->id)
            || self::peutOrganiser($utilisateur, $commission->instance);
    }

    /** Retrouve un membre validé par son matricule ou son téléphone. */
    public static function trouverMembre(string $saisie): ?Membre
    {
        $saisie = trim($saisie);
        $telephone = TelephoneNormalizer::normaliser($saisie);

        return Membre::query()
            ->where('statut', StatutMembre::Valide->value)
            ->where(fn ($query) => $query->where('matricule', mb_strtoupper($saisie))->when($telephone !== null, fn ($parTelephone) => $parTelephone->orWhere('telephone', $telephone)))
            ->first();
    }

    public function ajouterMembreCommission(CommissionComite $commission, Membre $membre, User $auteur): void
    {
        if (! self::dirige($auteur, $commission)) {
            throw new DomainException('Seul le chef de la commission gère ses membres.');
        }

        if ($commission->membres()->syncWithoutDetaching([$membre->id])['attached'] !== []) {
            JournalActivite::appartenance($commission, 'Membres', $membre, true);
        }

        $commission->instance->membres()->syncWithoutDetaching([$membre->id]);
    }

    public function retirerMembreCommission(CommissionComite $commission, Membre $membre, User $auteur): void
    {
        if (! self::dirige($auteur, $commission)) {
            throw new DomainException('Seul le chef de la commission gère ses membres.');
        }

        if ($commission->membres()->detach($membre->id) > 0) {
            JournalActivite::appartenance($commission, 'Membres', $membre, false);
        }
    }

    /** Retire un membre d'un comité (hors Comité directeur) ; la trace reste au journal. */
    public function retirerDuComite(Instance $instance, Membre $membre, User $auteur): void
    {
        if ($instance->estComiteDirecteur()) {
            $this->retirerDuComiteDirecteur($membre, $auteur);

            return;
        }

        if (! self::peutGerer($auteur, $instance)) {
            throw new DomainException('Seuls les organisateurs du comité gèrent ses membres.');
        }

        if ($instance->membres()->detach($membre->id) > 0) {
            JournalActivite::appartenance($instance, 'Membres', $membre, false);
        }
    }

    /** Le chef confie une tâche à un membre de sa commission, sans attendre une réunion. */
    public function confierTache(CommissionComite $commission, string $description, Membre $membre, ?string $echeance, User $auteur): ActionComite
    {
        if (! self::dirige($auteur, $commission)) {
            throw new DomainException('Seul le chef de la commission répartit ses tâches.');
        }

        if (! $commission->membres()->whereKey($membre->id)->exists() && $commission->chef_id !== $membre->id) {
            throw new DomainException('La tâche est confiée à un membre de la commission.');
        }

        $tache = ActionComite::query()->create([
            'instance_id' => $commission->instance_id,
            'commission_id' => $commission->id,
            'responsable_id' => $membre->id,
            'description' => $description,
            'echeance' => $echeance,
        ]);

        if ($membre->user_id !== null && ($compte = User::query()->find($membre->user_id)) !== null && $compte->id !== $auteur->id) {
            $compte->notify(new NotificationMembre(
                'Nouvelle tâche : commission '.$commission->nom,
                $description.($echeance !== null ? ' — pour le '.Carbon::parse($echeance)->format('d/m/Y') : '').'.',
                route('comites.index'),
                'Voir mes tâches',
            ));
        }

        return $tache;
    }

    public function changerStatut(ActionComite $action, string $statut, User $utilisateur): void
    {
        if (! array_key_exists($statut, ActionComite::STATUTS)) {
            throw new DomainException('Statut inconnu.');
        }

        if (! self::peutSuivre($utilisateur, $action)) {
            throw new DomainException('Seuls le responsable, le chef de la commission et les organisateurs suivent cette action.');
        }

        $action->update(['statut' => $statut]);
    }

    /**
     * L'avancement d'un comité : actions, réunions, décisions, et le compte à rebours.
     *
     * @return array{actions: int, faites: int, en_retard: int, taux: int, reunions: int, decisions: int, jours: int|null}
     */
    public function avancement(Instance $instance): array
    {
        $actions = $instance->actions()->where('statut', '!=', 'abandonnee')->get();
        $faites = $actions->where('statut', 'faite')->count();
        $cible = $instance->dateCible();

        return [
            'actions' => $actions->count(),
            'faites' => $faites,
            'en_retard' => $actions->filter(fn (ActionComite $action): bool => $action->estEnRetard())->count(),
            'taux' => $actions->count() > 0 ? (int) round($faites / $actions->count() * 100) : 0,
            'reunions' => $instance->reunions()->count(),
            'decisions' => $instance->decisions()->count(),
            'jours' => $cible !== null ? (int) now()->startOfDay()->diffInDays($cible->copy()->startOfDay(), false) : null,
        ];
    }

    /**
     * Chaque matin : rappel aux responsables 2 jours avant l'échéance et le jour même ; le lendemain d'un retard,
     * le chef est prévenu pour une tâche de sa commission, le président pour une action décidée en réunion.
     *
     * @return array{rappels: int, retards: int}
     */
    public function routineDuMatin(): array
    {
        $rappels = 0;
        $retards = 0;
        $aujourdhui = now()->startOfDay();

        $actions = ActionComite::query()
            ->with(['instance.president', 'responsable', 'commission.chef'])
            ->whereNotIn('statut', ActionComite::TERMINEES)
            ->whereNotNull('echeance')
            ->whereHas('instance', fn ($query) => $query->where('statut', 'active'))
            ->get();

        foreach ($actions as $action) {
            $ecart = (int) $aujourdhui->diffInDays($action->echeance?->copy()->startOfDay(), false);

            if (in_array($ecart, [2, 0], true) && ($compte = $action->compteResponsable()) !== null) {
                $compte->notify(new NotificationMembre(
                    $action->instance->nom,
                    'Action à mener'.($ecart === 0 ? ' aujourd\'hui' : ' dans 2 jours').' : '.$action->description.'.',
                    route('comites.index'),
                    'Voir mes actions',
                ));
                $rappels++;
            }

            $superviseur = $action->reunion_id === null && $action->commission !== null
                ? $action->commission->chef
                : $action->instance->president;

            if ($ecart === -1 && $superviseur !== null && $superviseur->user_id !== null && ($compte = User::query()->find($superviseur->user_id)) !== null) {
                $compte->notify(new NotificationMembre(
                    $action->instance->nom,
                    'Action en retard : '.$action->description.' ('.$action->responsableTexte().').',
                    route('comites.index'),
                    'Voir le comité',
                ));
                $retards++;
            }
        }

        return ['rappels' => $rappels, 'retards' => $retards];
    }
}
