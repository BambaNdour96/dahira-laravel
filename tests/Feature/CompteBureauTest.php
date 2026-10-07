<?php

use App\Models\Cellule;
use App\Models\User;
use App\Services\CompteBureauService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->super = User::factory()->create();
    $this->super->assignRole(['super_administrateur', 'administrateur']);

    $this->service = app(CompteBureauService::class);
});

function nouveauTresorier(CompteBureauService $service): User
{
    return $service->creer([
        'name' => 'Moussa Diop',
        'telephone' => '76 222 33 44',
        'role' => 'tresorier',
        'password' => 'provisoire1',
    ]);
}

it('réserve les comptes du bureau au super administrateur', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrateur');

    $this->actingAs($this->super)->get('/admin/comptes-bureau')->assertOk();
    $this->actingAs($admin)->get('/admin/comptes-bureau')->assertForbidden();
});

it('réserve les réglages au super administrateur', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrateur');

    $this->actingAs($this->super)->get('/admin/parametres')->assertOk();
    $this->actingAs($admin)->get('/admin/parametres')->assertForbidden();
});

it('crée un compte de Jewrine finance', function () {
    $user = nouveauTresorier($this->service);

    expect($user->telephone)->toBe('+221762223344')
        ->and($user->hasRole('tresorier'))->toBeTrue()
        ->and(Hash::check('provisoire1', $user->password))->toBeTrue();
});

it('rattache un Jewrine à sa cellule', function () {
    $cellule = Cellule::factory()->create();

    $user = $this->service->creer([
        'name' => 'Ibrahima Sarr',
        'telephone' => '77 444 55 66',
        'role' => 'responsable_section',
        'cellule_id' => $cellule->id,
        'password' => 'provisoire1',
    ]);

    expect($cellule->refresh()->responsable_id)->toBe($user->id);
});

it('donne un rôle à un membre déjà inscrit sans changer son mot de passe', function () {
    $membre = User::factory()->create(['telephone' => '+221763334455', 'password' => 'monmotdepasse']);
    $membre->assignRole('membre');

    $user = $this->service->creer([
        'name' => 'Autre nom',
        'telephone' => '763334455',
        'role' => 'tresorier',
        'password' => 'autre12345',
    ]);

    expect($user->id)->toBe($membre->id)
        ->and($user->hasAllRoles(['membre', 'tresorier']))->toBeTrue()
        ->and(Hash::check('monmotdepasse', $user->fresh()->password))->toBeTrue();
});

it('retire un compte du bureau en gardant son rôle de membre', function () {
    $user = nouveauTresorier($this->service);
    $user->assignRole('membre');

    $this->service->retirerDuBureau($user);

    expect($user->fresh()->getRoleNames()->all())->toBe(['membre']);
});

it('génère un nouveau mot de passe', function () {
    $user = nouveauTresorier($this->service);

    $nouveau = $this->service->reinitialiserMotDePasse($user);

    expect(Hash::check($nouveau, $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('provisoire1', $user->fresh()->password))->toBeFalse();
});

it('empêche un compte désactivé de se connecter', function () {
    $user = nouveauTresorier($this->service);

    $this->service->mettreAJour($user, ['name' => $user->name, 'role' => 'tresorier', 'actif' => false]);

    $this->post('/connexion', ['telephone' => '762223344', 'password' => 'provisoire1'])
        ->assertSessionHasErrors('telephone');
});

it('protège le compte du super administrateur', function () {
    $this->service->retirerDuBureau($this->super);
})->throws(DomainException::class);
