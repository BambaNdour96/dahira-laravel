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

it('rattache une commission à l\'inscription', function () {
    $ids = Commission::factory()->count(1)->create()->pluck('id')->all();

    $this->post('/inscription', inscriptionAvecCommissions($this->cellule->id, $ids))
        ->assertRedirect(route('inscription.merci'));

    expect(Membre::query()->firstOrFail()->commissions()->pluck('commissions.id')->sort()->values()->all())
        ->toBe(collect($ids)->sort()->values()->all());
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

it('réserve l\'écran des commissions à l\'administrateur', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrateur');
    $responsable = User::factory()->create();
    $responsable->assignRole('responsable_section');

    $this->actingAs($admin)->get('/admin/commissions')->assertOk();
    $this->actingAs($responsable)->get('/admin/commissions')->assertForbidden();
});

it('crée les six commissions officielles avec les données de production', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Commission::query()->orderBy('id')->pluck('nom')->all())->toBe([
        'Administration', 'Organisation', 'Finances', 'Communication', 'Sociale', 'Culturelle et religieuse',
    ]);
});
