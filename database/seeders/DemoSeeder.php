<?php

namespace Database\Seeders;

use App\Enums\StatutMembre;
use App\Enums\TypeTarif;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\Section;
use App\Models\Tarif;
use App\Services\StatutMembreService;
use Illuminate\Database\Seeder;

/**
 * Données fictives pour le développement uniquement.
 */
class DemoSeeder extends Seeder
{
    public function run(StatutMembreService $statuts): void
    {
        $sections = collect([
            ['nom' => 'Yoff', 'ville' => 'Dakar', 'pays' => 'Sénégal'],
            ['nom' => 'Touba', 'ville' => 'Touba', 'pays' => 'Sénégal'],
            ['nom' => 'Diaspora France', 'ville' => 'Paris', 'pays' => 'France'],
        ])->map(fn (array $s) => Section::firstOrCreate(['nom' => $s['nom']], $s));

        $annee = now()->year;
        $exercice = Exercice::firstOrCreate(
            ['libelle' => (string) $annee],
            ['date_debut' => "{$annee}-01-01", 'date_fin' => "{$annee}-12-31"],
        );

        Tarif::firstOrCreate(
            ['type' => TypeTarif::Adhesion, 'exercice_id' => null],
            ['libelle' => 'Adhésion', 'montant' => 2000],
        );
        Tarif::firstOrCreate(
            ['type' => TypeTarif::Cotisation, 'exercice_id' => $exercice->id],
            ['libelle' => "Cotisation {$annee}", 'montant' => 12000],
        );
        Tarif::firstOrCreate(
            ['type' => TypeTarif::CartePhysique, 'exercice_id' => null],
            ['libelle' => 'Carte physique', 'montant' => 1500],
        );

        if (Membre::query()->exists()) {
            return;
        }

        $membres = Membre::factory()->count(20)->recycle($sections)->create();

        $membres->take(8)->each(
            fn (Membre $membre) => $statuts->changer($membre, StatutMembre::Valide, null),
        );
    }
}
