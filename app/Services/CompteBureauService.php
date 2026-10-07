<?php

namespace App\Services;

use App\Models\Cellule;
use App\Models\JournalActivite;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comptes du bureau, gérés uniquement par le super administrateur.
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

    /**
     * Crée un compte du bureau. Si le numéro appartient déjà à un compte
     * (par exemple un membre inscrit), ce compte reçoit simplement le rôle.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function creer(array $donnees): User
    {
        $telephone = TelephoneNormalizer::normaliser((string) ($donnees['telephone'] ?? ''));

        if ($telephone === null) {
            throw new DomainException('Numéro de téléphone invalide.');
        }

        return DB::transaction(function () use ($donnees, $telephone): User {
            $user = User::query()->where('telephone', $telephone)->first();

            if ($user === null) {
                $user = User::create([
                    'name' => (string) $donnees['name'],
                    'telephone' => $telephone,
                    'email' => $donnees['email'] ?? null,
                    'password' => (string) $donnees['password'],
                    'actif' => true,
                ]);
            }

            $this->appliquerRole($user, (string) $donnees['role'], $donnees['cellule_id'] ?? null);

            return $user;
        });
    }

    /** @param  array<string, mixed>  $donnees */
    public function mettreAJour(User $user, array $donnees): User
    {
        return DB::transaction(function () use ($user, $donnees): User {
            $user->update([
                'name' => (string) $donnees['name'],
                'email' => $donnees['email'] ?? null,
            ]);

            // Le compte du super administrateur ne peut être ni désactivé ni modifié dans ses droits.
            if (! $user->hasRole('super_administrateur')) {
                $actif = (bool) ($donnees['actif'] ?? true);

                if ($actif !== (bool) $user->actif) {
                    $user->update(['actif' => $actif]);
                    JournalActivite::enregistrer($user, 'modifie', [
                        'apres' => ['compte' => $actif ? 'réactivé' : 'désactivé'],
                    ]);
                }

                $this->appliquerRole($user, (string) $donnees['role'], $donnees['cellule_id'] ?? null);
            }

            return $user;
        });
    }

    /** La personne quitte le bureau, mais reste membre si elle l'était. */
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
        $motDePasse = Str::password(10, symbols: false);
        $user->update(['password' => $motDePasse]);

        JournalActivite::enregistrer($user, 'modifie', ['apres' => ['mot_de_passe' => 'réinitialisé']]);

        return $motDePasse;
    }

    private function appliquerRole(User $user, string $role, mixed $celluleId): void
    {
        if (! array_key_exists($role, self::ROLES)) {
            throw new DomainException('Rôle inconnu.');
        }

        $avant = $user->getRoleNames()->join(', ');

        // On garde le rôle « membre » et le rôle « super administrateur » s'ils existent.
        $aGarder = $user->getRoleNames()->intersect(['membre', 'super_administrateur'])->values()->all();
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
