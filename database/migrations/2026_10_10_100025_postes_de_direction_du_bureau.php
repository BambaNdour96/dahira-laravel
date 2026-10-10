<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Le rôle « Administrateur » devient deux postes réels : Jewrine national et Secrétaire général.
 * Chaque administrateur existant reçoit le poste qui correspond à sa fiche de membre.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['jewrine_national', 'secretaire_general'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        if (! Role::query()->where('name', 'administrateur')->exists()) {
            return;
        }

        User::role('administrateur')->with('membre.poste')->get()
            ->reject(fn (User $user): bool => $user->hasRole('super_administrateur'))
            ->each(function (User $user): void {
                $poste = mb_strtolower((string) $user->membre?->poste?->nom);
                $user->assignRole(str_contains($poste, 'secr') ? 'secretaire_general' : 'jewrine_national');
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Les deux postes restent : sans eux, la direction perdrait ses droits propres.
    }
};
