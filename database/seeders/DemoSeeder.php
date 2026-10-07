<?php

namespace Database\Seeders;

use App\Enums\StatutMembre;
use App\Enums\TypeTarif;
use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\Poste;
use App\Models\Tarif;
use App\Models\User;
use App\Services\CompteBureauService;
use App\Services\StatutMembreService;
use Illuminate\Database\Seeder;

/**
 * Données fictives pour le développement uniquement.
 */
class DemoSeeder extends Seeder
{
    public function run(StatutMembreService $statuts, CompteBureauService $bureau): void
    {
        $cellules = Cellule::query()->get();
        $kaolack = Cellule::query()->where('nom', 'Kaolack')->firstOrFail();

        foreach (['Administration', 'Organisation', 'Finances', 'Communication', 'Sociale', 'Culturelle et religieuse'] as $nom) {
            Commission::firstOrCreate(['nom' => $nom]);
        }

        $annee = now()->year;
        $exercice = Exercice::firstOrCreate(
            ['libelle' => (string) $annee],
            ['date_debut' => "{$annee}-01-01", 'date_fin' => "{$annee}-12-31"],
        );

        Tarif::firstOrCreate(
            ['type' => TypeTarif::Cotisation, 'exercice_id' => $exercice->id],
            ['libelle' => "Cotisation {$annee}", 'montant' => 12000],
        );

        if (Membre::query()->exists()) {
            return;
        }

        // Le bureau de démonstration : des membres validés, puis nommés.
        $this->membreDuBureau($statuts, $bureau, '+221770000001', 'Modou', 'Fall', $kaolack, 'Jewrine cellule', 'responsable_section');
        $this->membreDuBureau($statuts, $bureau, '+221770000002', 'Awa', 'Sarr', $kaolack, 'Jewrine finance', 'tresorier');

        // Les autres membres : 8 validés, 4 acceptés qui doivent payer, les autres en attente.
        $membres = Membre::factory()->count(20)->recycle($cellules)->create();
        $commissions = Commission::all();

        $membres->each(fn (Membre $membre) => $membre->commissions()->sync(
            $commissions->random(random_int(0, Commission::MAX_PAR_MEMBRE))->pluck('id'),
        ));

        $membres->take(8)->each(
            fn (Membre $membre) => $statuts->changer($membre, StatutMembre::Valide, null),
        );
        $membres->slice(8, 4)->each(
            fn (Membre $membre) => $statuts->changer($membre, StatutMembre::Accepte, null),
        );
    }

    private function membreDuBureau(
        StatutMembreService $statuts,
        CompteBureauService $bureau,
        string $telephone,
        string $prenom,
        string $nom,
        Cellule $cellule,
        string $poste,
        string $role,
    ): void {
        $user = User::updateOrCreate(['telephone' => $telephone], [
            'name' => $prenom.' '.$nom,
            'password' => 'password',
            'actif' => true,
        ]);
        $user->syncRoles(['membre']);

        $membre = Membre::factory()->for($user)->for($cellule)->create([
            'prenom' => $prenom,
            'nom' => $nom,
            'telephone' => $telephone,
            'poste_id' => Poste::query()->where('nom', $poste)->value('id'),
        ]);

        $statuts->changer($membre, StatutMembre::Valide, null);
        $bureau->nommer($membre->refresh(), $role, $role === 'responsable_section' ? $cellule->id : null);
    }
}
