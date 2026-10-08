<?php

namespace Database\Seeders;

use App\Models\Commission;
use Illuminate\Database\Seeder;

/**
 * Les six commissions officielles de la Daara, créées aussi en production.
 */
class CommissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Administration', 'Organisation', 'Finances', 'Communication', 'Sociale', 'Culturelle et religieuse'] as $nom) {
            Commission::query()->firstOrCreate(['nom' => $nom]);
        }
    }
}
