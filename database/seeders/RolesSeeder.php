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

        foreach (['administrateur', 'tresorier', 'responsable_section', 'membre'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
