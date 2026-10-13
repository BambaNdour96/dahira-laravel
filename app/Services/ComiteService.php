<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\ActionComite;
use App\Models\CommissionComite;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\PresenceReunion;
use App\Models\Reunion;
use App\Models\User;
use App\Notifications\NotificationMembre;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Les comités de la Daara : le Comité directeur (tenu par le super administrateur), les CDGMT et autres comités,
 * leurs commissions, leurs réunions et leurs PV.
 */
final class ComiteService
{
    /** Postes qui créent un comité et en désignent le président. */
    public const DIRECTION = ['super_administrateur', 'jewrine_national', 'secretaire_general'];

    public static function estDuComiteDirecteur(User $utilisateur): bool
    {
        return $utilisateur->hasAnyRole(['comite_directeur', 'super_administrateur']);
    }

    public static function peutCreer(User $utilisateur): bool
    {
        return $utilisateur->hasAnyRole(self::DIRECTION);
    }

    /** Gérer un comité : la direction, ou son président (sauf le Comité directeur, tenu par le super administrateur). */
    public static function peutGerer(User $utilisateur, Instance $instance): bool
    {
        if ($utilisateur->hasRole('super_administrateur')) {
            return true;
        }

        if ($instance->estComiteDirecteur()) {
            return false;
        }

        return $utilisateur->hasAnyRole(self::DIRECTION)
            || ($instance->president !== null && $instance->president->user_id === $utilisateur->id);
    }

    /** Organiser les réunions : comme gérer, plus la direction pour le Comité directeur. */
    public static function peutOrganiser(User $utilisateur, Instance $instance): bool
    {
        return self::peutGerer($utilisateur, $instance)
            || ($instance->estComiteDirecteur() && $utilisateur->hasAnyRole(self::DIRECTION));
    }

    /** Rédiger et publier le PV : l'organisateur ou le secrétaire de séance, tant qu'il n'est pas publié. */
    public static function peutRediger(User $utilisateur, Reunion $reunion): bool
    {
        if ($reunion->estPubliee()) {
            return false;
        }

        return self::peutOrganiser($utilisateur, $reunion->instance)
            || ($reunion->secretaire !== null && $reunion->secretaire->user_id === $utilisateur->id);
    }

    /** Lire un comité, ses PV et ses décisions : ses membres et le Comité directeur. */
    public static function peutLire(User $utilisateur, Instance $instance): bool
    {
        if (self::estDuComiteDirecteur($utilisateur) || $utilisateur->hasAnyRole(self::DIRECTION)) {
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
        if (! $auteur->hasRole('super_administrateur')) {
            throw new DomainException('Seul le super administrateur tient la liste du Comité directeur.');
        }

        $comite = Instance::comiteDirecteur();
        $comite->membres()->sync($membres);
        $this->synchroniserRoles();
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

    /** Le rôle « comite_directeur » suit exactement la liste du Comité directeur. */
    public function synchroniserRoles(): void
    {
        $comptes = Instance::comiteDirecteur()->membres()->whereNotNull('user_id')->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();

        foreach (User::role('comite_directeur')->get() as $utilisateur) {
            if (! in_array($utilisateur->id, $comptes, true)) {
                $utilisateur->removeRole('comite_directeur');
            }
        }

        foreach (User::query()->whereIn('id', $comptes)->get() as $utilisateur) {
            $utilisateur->assignRole('comite_directeur');
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

    /** Recopie les commissions du comité précédent du même type (sans les chefs, qui changent chaque année). */
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
            CommissionComite::query()->firstOrCreate(
                ['instance_id' => $instance->id, 'nom' => $commission->nom],
                ['mission' => $commission->mission, 'ordre' => $commission->ordre],
            );
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

    public function messageConvocation(Reunion $reunion): string
    {
        $lignes = [
            '*'.$reunion->instance->nom.'*',
            'Convocation : '.$reunion->titre,
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
            $reunion->presences()->delete();

            foreach (array_unique($presents) as $id) {
                PresenceReunion::query()->create(['reunion_id' => $reunion->id, 'membre_id' => $id, 'statut' => 'present']);
            }

            foreach (array_diff(array_unique($excuses), $presents) as $id) {
                PresenceReunion::query()->create(['reunion_id' => $reunion->id, 'membre_id' => $id, 'statut' => 'excuse']);
            }
        });
    }

    /**
     * Le quorum de la réunion.
     *
     * @return array{presents: int, membres: int, requis: int, atteint: bool}
     */
    public function quorum(Reunion $reunion): array
    {
        $membres = $reunion->instance->membres()->count();
        $presents = $reunion->presences()->where('statut', 'present')->count();
        $requis = (int) ceil($membres * $reunion->instance->quorum / 100);

        return ['presents' => $presents, 'membres' => $membres, 'requis' => $requis, 'atteint' => $membres > 0 && $presents >= $requis];
    }

    /** Publie le PV : il est figé, et les membres du comité sont prévenus. */
    public function publier(Reunion $reunion, User $auteur): void
    {
        if (! self::peutRediger($auteur, $reunion)) {
            throw new DomainException('Seuls le secrétaire de séance et les organisateurs publient le PV.');
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
    private function comptesDesMembres(Instance $instance): Collection
    {
        return User::query()->whereIn('id', $instance->membres()->whereNotNull('user_id')->select('user_id'))->get();
    }

    /** Peut-on changer le statut de cette action ? L'organisateur, le responsable ou le chef de la commission. */
    public static function peutSuivre(User $utilisateur, ActionComite $action): bool
    {
        if (self::peutOrganiser($utilisateur, $action->instance)) {
            return true;
        }

        return $action->compteResponsable()?->id === $utilisateur->id;
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
        $actions = $instance->actions()->get();
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
     * Chaque matin : rappel aux responsables 2 jours avant l'échéance et le jour même ; le lendemain d'un retard, le président est prévenu.
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
            ->where('statut', '!=', 'faite')
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

            $president = $action->instance->president;

            if ($ecart === -1 && $president !== null && $president->user_id !== null && ($compte = User::query()->find($president->user_id)) !== null) {
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
