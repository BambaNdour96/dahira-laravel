<?php

namespace Database\Seeders;

use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\Poste;
use App\Models\User;
use App\Services\CommissionService;
use App\Services\NominationService;
use App\Services\StatutMembreService;
use Illuminate\Database\Seeder;

/**
 * Données fictives pour le développement uniquement.
 */
class DemoSeeder extends Seeder
{
    public function run(StatutMembreService $statuts, NominationService $bureau): void
    {
        $cellules = Cellule::query()->get();
        $kaolack = Cellule::query()->where('nom', 'Kaolack')->firstOrFail();
        $dakar = Cellule::query()->where('nom', 'Dakar')->firstOrFail();

        $this->call(CommissionsSeeder::class);

        $annee = now()->year;
        $exercice = Exercice::firstOrCreate(
            ['libelle' => (string) $annee],
            ['date_debut' => "{$annee}-01-01", 'date_fin' => "{$annee}-12-31"],
        );

        if (Membre::query()->exists()) {
            return;
        }

        // Le bureau de démonstration : des membres validés, puis nommés.
        $this->membreDuBureau($statuts, $bureau, '+221770000001', 'Modou', 'Fall', $kaolack, 'jewrine_cellule');
        $this->membreDuBureau($statuts, $bureau, '+221770000002', 'Awa', 'Sarr', $kaolack, 'jewrine_finance');
        $this->membreDuBureau($statuts, $bureau, '+221770000003', 'Ibrahima', 'Diop', $dakar, 'jewrine_cellule');

        // Les autres membres : 8 validés, 4 acceptés qui doivent payer, les autres en attente.
        $membres = Membre::factory()->count(20)->recycle($cellules)->create();
        $commissions = Commission::all();

        // Chaque membre de démonstration exprime un souhait pour une commission, au hasard.
        $membres->each(fn (Membre $membre) => app(CommissionService::class)->souhaiter($membre, $commissions->random()));

        $membres->take(8)->each(
            fn (Membre $membre) => $statuts->changer($membre, StatutMembre::Valide, null),
        );
        $membres->slice(8, 4)->each(
            fn (Membre $membre) => $statuts->changer($membre, StatutMembre::Accepte, null),
        );
    }

    private function membreDuBureau(
        StatutMembreService $statuts,
        NominationService $bureau,
        string $telephone,
        string $prenom,
        string $nom,
        Cellule $cellule,
        string $poste,
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
        ]);

        $statuts->changer($membre, StatutMembre::Valide, null);

        // Les nominations de démonstration sont faites au nom du super administrateur.
        $auteur = User::role('super_administrateur')->first();

        if ($auteur !== null) {
            $bureau->nommer($user, Poste::query()->where('code', $poste)->firstOrFail(), $cellule, $auteur);
        }
    }
}
