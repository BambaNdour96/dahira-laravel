<?php

namespace App\Services;

use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\User;
use App\Support\AffichageMotDePasse;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Comptes du bureau, gérés uniquement par le super administrateur.
 * Règle : on ne nomme au bureau qu'un membre déjà validé.
 * Chaque changement est noté au journal d'activité.
 */
final class CompteBureauService
{
    /** Rôles que le super administrateur peut attribuer. */
    public const ROLES = [
        'administrateur' => 'Administrateur (Jewrine national, Secrétaire général…)',
        'tresorier' => 'Jewrine finance',
        'responsable_section' => 'Jewrine de cellule',
    ];

    /** Libellés de tous les rôles, pour l'affichage. */
    public const LIBELLES = [
        'super_administrateur' => 'Super administrateur',
        'administrateur' => 'Administrateur',
        'tresorier' => 'Jewrine finance',
        'responsable_section' => 'Jewrine de cellule',
        'membre' => 'Membre',
    ];

    /** Rôles qui donnent accès au panneau du bureau. */
    public const ROLES_BUREAU = ['super_administrateur', 'administrateur', 'tresorier', 'responsable_section'];

    /** Nomme au bureau un membre validé : il garde sa fiche, sa carte et son mot de passe. */
    public function nommer(Membre $membre, string $role, mixed $celluleId = null): User
    {
        if ($membre->statut !== StatutMembre::Valide) {
            throw new DomainException('Seul un membre validé peut être nommé au bureau.');
        }

        $user = $membre->user;

        if ($user === null) {
            throw new DomainException('Ce membre n\'a pas de compte de connexion.');
        }

        if ($user->hasRole('super_administrateur')) {
            throw new DomainException('Le compte du super administrateur ne se modifie pas ici.');
        }

        DB::transaction(fn () => $this->appliquerRole($user, $role, $celluleId));

        return $user;
    }

    /** @param  array<string, mixed>  $donnees */
    public function mettreAJour(User $user, array $donnees): User
    {
        if ($user->hasRole('super_administrateur')) {
            throw new DomainException('Le compte du super administrateur ne se modifie pas ici.');
        }

        return DB::transaction(function () use ($user, $donnees): User {
            $actif = (bool) ($donnees['actif'] ?? true);

            if ($actif !== (bool) $user->actif) {
                $user->update(['actif' => $actif]);
                JournalActivite::enregistrer($user, 'modifie', [
                    'apres' => ['compte' => $actif ? 'réactivé' : 'désactivé'],
                ]);
            }

            $this->appliquerRole($user, (string) $donnees['role'], $donnees['cellule_id'] ?? null);

            return $user;
        });
    }

    /** La personne quitte le bureau, mais reste membre. */
    public function retirerDuBureau(User $user): void
    {
        if ($user->hasRole('super_administrateur')) {
            throw new DomainException('Le compte du super administrateur ne peut pas être retiré du bureau.');
        }

        DB::transaction(function () use ($user): void {
            $avant = $user->getRoleNames()->join(', ');

            $user->syncRoles($user->getRoleNames()->intersect(['membre'])->values()->all());
            Cellule::query()->where('responsable_id', $user->id)->update(['responsable_id' => null]);

            JournalActivite::enregistrer($user, 'modifie', [
                'avant' => ['roles' => $avant],
                'apres' => ['roles' => 'retiré du bureau'],
            ]);
        });
    }

    /** Génère un mot de passe provisoire, à transmettre à la personne. */
    public function reinitialiserMotDePasse(User $user): string
    {
        $motDePasse = AffichageMotDePasse::generer();
        $user->forceFill(['password' => $motDePasse, 'doit_changer_mot_de_passe' => true])->save();

        // Le compte est débloqué, et toutes ses connexions ouvertes sont fermées.
        RateLimiter::clear('blocage-compte:'.$user->telephone);
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        JournalActivite::enregistrer($user, 'modifie', ['apres' => ['mot_de_passe' => 'réinitialisé']]);

        return $motDePasse;
    }

    private function appliquerRole(User $user, string $role, mixed $celluleId): void
    {
        if (! array_key_exists($role, self::ROLES)) {
            throw new DomainException('Rôle inconnu.');
        }

        $avant = $user->getRoleNames()->join(', ');

        // On garde le rôle « membre ».
        $aGarder = $user->getRoleNames()->intersect(['membre'])->values()->all();
        $user->syncRoles([...$aGarder, $role]);

        Cellule::query()->where('responsable_id', $user->id)->update(['responsable_id' => null]);

        if ($role === 'responsable_section') {
            if (blank($celluleId)) {
                throw new DomainException('Choisissez la cellule de ce Jewrine.');
            }

            Cellule::query()->whereKey($celluleId)->update(['responsable_id' => $user->id]);
        }

        $apres = $user->getRoleNames()->join(', ');

        if ($avant !== $apres) {
            JournalActivite::enregistrer($user, 'modifie', [
                'avant' => ['roles' => $avant],
                'apres' => ['roles' => $apres],
            ]);
        }
    }
}
