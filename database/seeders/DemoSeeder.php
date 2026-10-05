<?php

namespace Database\Seeders;

use App\Enums\StatutMembre;
use App\Enums\TypeTarif;
use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\Tarif;
use App\Models\User;
use App\Services\StatutMembreService;
use Illuminate\Database\Seeder;

/**
 * Données fictives pour le développement uniquement.
 */
class DemoSeeder extends Seeder
{
    public function run(StatutMembreService $statuts): void
    {
        $cellules = collect([
            ['nom' => 'Kaolack Centre', 'ville' => 'Kaolack', 'pays' => 'Sénégal'],
            ['nom' => 'Médina Baye', 'ville' => 'Kaolack', 'pays' => 'Sénégal'],
            ['nom' => 'Dakar', 'ville' => 'Dakar', 'pays' => 'Sénégal'],
        ])->map(fn (array $c) => Cellule::firstOrCreate(['nom' => $c['nom']], $c));

        foreach (['Organisation', 'Finances', 'Communication', 'Sociale', 'Culturelle et religieuse'] as $nom) {
            Commission::firstOrCreate(['nom' => $nom]);
        }

        $responsable = User::updateOrCreate(['telephone' => '+221770000001'], [
            'name' => 'Jewrine Kaolack Centre (démo)',
            'password' => 'password',
            'actif' => true,
        ]);
        $responsable->syncRoles(['responsable_section']);
        $cellules->first()?->update(['responsable_id' => $responsable->id]);

        $tresorier = User::updateOrCreate(['telephone' => '+221770000002'], [
            'name' => 'Trésorier (démo)',
            'password' => 'password',
            'actif' => true,
        ]);
        $tresorier->syncRoles(['tresorier']);

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

        $membres = Membre::factory()->count(20)->recycle($cellules)->create();
        $commissions = Commission::all();

        $membres->each(fn (Membre $membre) => $membre->commissions()->sync(
            $commissions->random(random_int(0, Commission::MAX_PAR_MEMBRE))->pluck('id'),
        ));

        $membres->take(8)->each(
            fn (Membre $membre) => $statuts->changer($membre, StatutMembre::Valide, null),
        );
    }
}
