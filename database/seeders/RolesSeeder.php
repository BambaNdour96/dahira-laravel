<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['super_administrateur', 'administrateur', 'jewrine_national', 'secretaire_general', 'tresorier', 'responsable_section', 'jewrine_femmes', 'suivi_evaluation', 'representant_finance', 'membre'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
