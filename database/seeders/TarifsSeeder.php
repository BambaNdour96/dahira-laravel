<?php

namespace Database\Seeders;

use App\Enums\TypeTarif;
use App\Models\Tarif;
use Illuminate\Database\Seeder;

/**
 * Tarifs officiels de la Daara, modifiables dans Paramètres → Tarifs.
 */
class TarifsSeeder extends Seeder
{
    public function run(): void
    {
        Tarif::firstOrCreate(
            ['type' => TypeTarif::Adhesion, 'exercice_id' => null],
            ['libelle' => 'Frais de carte de membre', 'montant' => 1000],
        );
    }
}
