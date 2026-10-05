<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\TelephoneNormalizer;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesSeeder::class);
        $this->call(PostesSeeder::class);
        $this->creerAdministrateur();

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }

    private function creerAdministrateur(): void
    {
        $config = config('dahira.admin');
        $telephone = TelephoneNormalizer::normaliser($config['telephone'] ?? null);

        if ($telephone === null || empty($config['password'])) {
            $this->command->warn('ADMIN_TELEPHONE ou ADMIN_PASSWORD manquant dans .env : aucun administrateur créé.');

            return;
        }

        $admin = User::updateOrCreate(['telephone' => $telephone], [
            'name' => $config['nom'],
            'email' => $config['email'],
            'password' => $config['password'],
            'actif' => true,
        ]);

        $admin->assignRole('administrateur');
    }
}
