<?php

use App\Models\Cellule;
use App\Models\Membre;
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

function membreValideAvecCompte(string $telephone = '+221762223344', string $motDePasse = 'monmotdepasse'): Membre
{
    $user = User::factory()->create(['telephone' => $telephone, 'password' => $motDePasse]);
    $user->assignRole('membre');

    return Membre::factory()->for($user)->valide()->create();
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

it('nomme un membre validé Jewrine finance, sans toucher à son mot de passe', function () {
    $membre = membreValideAvecCompte();

    $user = $this->service->nommer($membre, 'tresorier');

    expect($user->hasAllRoles(['membre', 'tresorier']))->toBeTrue()
        ->and(Hash::check('monmotdepasse', $user->fresh()->password))->toBeTrue();
});

it('refuse de nommer un membre qui n\'est pas encore validé', function () {
    $user = User::factory()->create();
    $user->assignRole('membre');
    $membre = Membre::factory()->for($user)->create();

    $this->service->nommer($membre, 'tresorier');
})->throws(DomainException::class);

it('rattache un Jewrine à sa cellule', function () {
    $cellule = Cellule::factory()->create();

    $user = $this->service->nommer(membreValideAvecCompte(), 'responsable_section', $cellule->id);

    expect($cellule->refresh()->responsable_id)->toBe($user->id);
});

it('retire un compte du bureau en gardant son rôle de membre', function () {
    $user = $this->service->nommer(membreValideAvecCompte(), 'tresorier');

    $this->service->retirerDuBureau($user);

    expect($user->fresh()->getRoleNames()->all())->toBe(['membre']);
});

it('génère un nouveau mot de passe', function () {
    $user = $this->service->nommer(membreValideAvecCompte(), 'tresorier');

    $nouveau = $this->service->reinitialiserMotDePasse($user);

    expect(Hash::check($nouveau, $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('monmotdepasse', $user->fresh()->password))->toBeFalse();
});

it('empêche un compte désactivé de se connecter', function () {
    $user = $this->service->nommer(membreValideAvecCompte(), 'tresorier');

    $this->service->mettreAJour($user, ['role' => 'tresorier', 'actif' => false]);

    $this->post('/connexion', ['telephone' => '762223344', 'password' => 'monmotdepasse'])
        ->assertSessionHasErrors('telephone');
});

it('protège le compte du super administrateur', function () {
    $this->service->retirerDuBureau($this->super);
})->throws(DomainException::class);

it('montre « Panneau du bureau » sur le site aux seuls membres du bureau', function () {
    $membre = membreValideAvecCompte();
    $simple = membreValideAvecCompte('+221769998877');

    $this->service->nommer($membre, 'tresorier');

    $this->actingAs($membre->user)->get('/mon-espace')->assertSee('Panneau du bureau');
    $this->actingAs($simple->user)->get('/mon-espace')->assertDontSee('Panneau du bureau');
});

it('montre « Mon compte de membre » dans le panneau', function () {
    $membre = membreValideAvecCompte();
    $this->service->nommer($membre, 'administrateur');

    $this->actingAs($membre->user)->get('/admin')->assertOk()->assertSee('Mon compte de membre');
});
