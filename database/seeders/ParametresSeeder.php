<?php

namespace Database\Seeders;

use App\Models\Parametre;
use Illuminate\Database\Seeder;

class ParametresSeeder extends Seeder
{
    public function run(): void
    {
        $parametres = [
            'whatsapp_national' => 'Lien du groupe WhatsApp national',
            'wave_lien_frais_carte' => 'Lien Wave : frais de carte (1 000 FCFA)',
            'wave_lien_1_mois' => 'Lien Wave : cotisation 1 mois (1 000 FCFA)',
            'wave_lien_2_mois' => 'Lien Wave : cotisation 2 mois (2 000 FCFA)',
            'wave_lien_3_mois' => 'Lien Wave : cotisation 3 mois (3 000 FCFA)',
            'wave_lien_paiement' => 'Lien Wave général (secours, sans montant fixe)',
            'orange_money_numero' => 'Numéro Orange Money de la Daara (pour recevoir les paiements)',
        ];

        foreach ($parametres as $cle => $libelle) {
            Parametre::firstOrCreate(['cle' => $cle], ['libelle' => $libelle]);
        }

        Parametre::firstOrCreate(['cle' => 'maintenance'], ['libelle' => 'Mode maintenance', 'valeur' => '0']);
    }
}
