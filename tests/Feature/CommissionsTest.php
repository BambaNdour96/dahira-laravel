<?php

use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Membre;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Storage::fake('local');
    $this->cellule = Cellule::factory()->create();
});

function inscriptionAvecCommissions(int $celluleId, array $commissions): array
{
    return [
        'prenom' => 'Modou',
        'nom' => 'Sarr',
        'sexe' => 'H',
        'date_naissance' => '1990-05-20',
        'adresse' => 'Médina Baye',
        'ville' => 'Kaolack',
        'pays_residence' => 'Sénégal',
        'telephone' => '78 111 22 33',
        'cellule_id' => $celluleId,
        'commissions' => $commissions,
        'photo' => UploadedFile::fake()->image('photo.jpg', 900, 900),
        'password' => 'motdepasse1',
        'password_confirmation' => 'motdepasse1',
        'consentement' => '1',
    ];
}

it('enregistre la commission choisie à l\'inscription comme un souhait, à accepter par son Jewrine', function () {
    $ids = Commission::factory()->count(1)->create()->pluck('id')->all();

    $this->post('/inscription', inscriptionAvecCommissions($this->cellule->id, $ids))
        ->assertRedirect(route('inscription.merci'));

    $membre = Membre::query()->firstOrFail();

    expect($membre->commissions()->count())->toBe(0)
        ->and($membre->appartenances()->souhaits()->pluck('commission_id')->all())->toBe($ids);
});

it('refuse deux commissions', function () {
    $ids = Commission::factory()->count(2)->create()->pluck('id')->all();

    $this->post('/inscription', inscriptionAvecCommissions($this->cellule->id, $ids))
        ->assertSessionHasErrors('commissions');

    expect(Membre::query()->count())->toBe(0);
});

it('refuse une commission qui n\'est plus proposée', function () {
    $fermee = Commission::factory()->create(['actif' => false]);

    $this->post('/inscription', inscriptionAvecCommissions($this->cellule->id, [$fermee->id]))
        ->assertSessionHasErrors('commissions.0');
});

it('exige une commission à l\'inscription', function () {
    $this->post('/inscription', inscriptionAvecCommissions($this->cellule->id, []))
        ->assertSessionHasErrors('commissions');
});

it('réserve l\'écran des commissions au super administrateur', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_administrateur');
    $responsable = User::factory()->create();
    $responsable->assignRole('jewrine_cellule');

    $this->actingAs($admin)->get('/admin/commissions')->assertOk();
    $this->actingAs($responsable)->get('/admin/commissions')->assertForbidden();
});

it('crée les sept commissions permanentes officielles, chacune avec son espace', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Commission::query()->orderBy('nom')->pluck('nom')->all())->toBe([
        'Administration', 'Communication', 'Culturelle', 'Finance', 'Organisation', 'Sociale', 'Suivi et Évaluation',
    ])
        ->and(Commission::query()->doesntHave('comite')->exists())->toBeFalse()
        ->and(Commission::query()->where('code', 'suivi_evaluation')->value('nom'))->toBe('Suivi et Évaluation');
});
