<?php

namespace Database\Seeders;

use App\Models\Parametre;
use Illuminate\Database\Seeder;

class ParametresSeeder extends Seeder
{
    public function run(): void
    {
        Parametre::firstOrCreate(
            ['cle' => 'whatsapp_national'],
            ['libelle' => 'Lien du groupe WhatsApp national'],
        );

        Parametre::firstOrCreate(
            ['cle' => 'wave_lien_paiement'],
            ['libelle' => 'Lien de paiement Wave Business (frais de carte)'],
        );

        Parametre::firstOrCreate(
            ['cle' => 'maintenance'],
            ['libelle' => 'Mode maintenance', 'valeur' => '0'],
        );
    }
}
