<?php

namespace App\Services;

use App\Models\Cellule;
use App\Models\Instance;
use App\Models\Nomination;
use App\Models\RepresentantFinance;
use App\Models\User;
use App\Support\Capacites;
use Spatie\Permission\PermissionRegistrar;

/**
 * Les casquettes d'une personne et ce qu'elles lui permettent.
 *
 * Les rôles de chacun sont recalculés à partir de ses casquettes en cours : nominations, représentation
 * finance, Comité directeur. Le super administrateur, le rôle « membre » et, jusqu'au lot R1b,
 * le rôle « jewrine_femmes » ne sont pas gérés ici.
 *
 * Service à portée de requête (voir AppServiceProvider) : les cellules dirigées sont gardées en mémoire.
 */
final class CasquettesService
{
    /** Les rôles recalculés à partir des casquettes. */
    public const GEREES = [...Capacites::POSTES, 'representant_finance', 'comite_directeur'];

    /** @var array<int, list<int>> */
    private array $cellules = [];

    /** Recalcule les rôles de la personne d'après ses casquettes en cours. */
    public function synchroniser(User $utilisateur): void
    {
        $voulus = Nomination::query()
            ->where('user_id', $utilisateur->id)
            ->join('postes', 'postes.id', '=', 'nominations.poste_id')
            ->whereNotNull('postes.code')
            ->pluck('postes.code')
            ->all();

        if (RepresentantFinance::query()->where('user_id', $utilisateur->id)->exists()) {
            $voulus[] = 'representant_finance';
        }

        $membre = $utilisateur->membre;
        $comite = Instance::query()->where('type', Instance::COMITE_DIRECTEUR)->first();

        if ($membre !== null && $comite !== null && $comite->membres()->whereKey($membre->id)->exists()) {
            $voulus[] = 'comite_directeur';
        }

        $autres = $utilisateur->getRoleNames()->diff(self::GEREES)->values()->all();
        $utilisateur->syncRoles(array_values(array_unique([...$autres, ...$voulus])));

        unset($this->cellules[$utilisateur->id]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Les cellules que cette personne dirige : Jewrine de cellule, et Jewrine des Sokhnas jusqu'au lot R1b.
     *
     * @return list<int>
     */
    public function cellulesDirigees(User $utilisateur): array
    {
        return $this->cellules[$utilisateur->id] ??= array_values(array_unique(array_map('intval', [
            ...Cellule::query()->dirigeesPar($utilisateur)->pluck('id')->all(),
            ...Cellule::query()->where('jewrine_femmes_id', $utilisateur->id)->pluck('id')->all(),
        ])));
    }

    /** Est-il le Jewrine de cette cellule (nomination en cours) ? */
    public function estJewrineDe(User $utilisateur, ?int $celluleId): bool
    {
        return $celluleId !== null && Cellule::query()->dirigeesPar($utilisateur)->whereKey($celluleId)->exists();
    }

    /** Dirige-t-il cette cellule (Jewrine de cellule, ou Jewrine des Sokhnas jusqu'au lot R1b) ? */
    public function dirige(User $utilisateur, ?int $celluleId): bool
    {
        return $celluleId !== null && in_array($celluleId, $this->cellulesDirigees($utilisateur), true);
    }

    /**
     * A-t-il cette capacité pour toute la Daara, ou pour cette cellule ?
     * Sans cellule, seule la capacité pour toute la Daara compte.
     */
    public function peut(User $utilisateur, string $capacite, ?int $celluleId = null): bool
    {
        return $utilisateur->can($capacite)
            || ($celluleId !== null && $utilisateur->can($capacite.'.cellule') && $this->dirige($utilisateur, $celluleId));
    }

    /** A-t-il cette capacité, pour toute la Daara ou au moins pour sa cellule ? */
    public function peutQuelquePart(User $utilisateur, string $capacite): bool
    {
        return $utilisateur->can($capacite) || $utilisateur->can($capacite.'.cellule');
    }

    /** Limité à ses cellules (il a la capacité seulement pour sa cellule) ? */
    public function limiteASesCellules(User $utilisateur, string $capacite): bool
    {
        return ! $utilisateur->can($capacite);
    }

    /** Un responsable : super administrateur, ou titulaire d'un poste en cours (ou Jewrine des Sokhnas jusqu'au lot R1b). */
    public function estDuBureau(User $utilisateur): bool
    {
        return $utilisateur->hasRole('super_administrateur')
            || $utilisateur->hasRole('jewrine_femmes')
            || Nomination::query()->where('user_id', $utilisateur->id)->exists();
    }

    public static function estSuperAdministrateur(User $utilisateur): bool
    {
        return $utilisateur->hasRole('super_administrateur');
    }
}
