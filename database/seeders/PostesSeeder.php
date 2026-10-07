<?php

namespace Database\Seeders;

use App\Models\Poste;
use Illuminate\Database\Seeder;

class PostesSeeder extends Seeder
{
    public function run(): void
    {
        $postes = [
            'Jewrine national', 'Secrétaire général', 'Jewrine finance', 'Jewrine cellule',
            'Président de commission', 'Commissaire', 'Responsable technique', 'Membre',
        ];

        foreach ($postes as $ordre => $nom) {
            Poste::firstOrCreate(['nom' => $nom], ['ordre' => $ordre + 1]);
        }
    }
}
