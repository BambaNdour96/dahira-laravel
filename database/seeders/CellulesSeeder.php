<?php

namespace Database\Seeders;

use App\Models\Cellule;
use Illuminate\Database\Seeder;

/**
 * Les cellules officielles de la Daara.
 */
class CellulesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Dakar', 'Kaolack'] as $nom) {
            Cellule::firstOrCreate(['nom' => $nom], ['ville' => $nom, 'pays' => 'Sénégal']);
        }
    }
}
