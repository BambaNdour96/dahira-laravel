<?php

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    // On lève la limite par adresse IP, pour tester le blocage par compte.
    RateLimiter::for('connexion', fn () => Limit::none());

    $this->user = User::factory()->create(['telephone' => '+221778889900', 'password' => 'lebonmotdepasse']);
    $this->user->assignRole('membre');

    $this->super = User::factory()->create();
    $this->super->assignRole(['super_administrateur', 'administrateur']);
});

function tenterConnexion(string $motDePasse): TestResponse
{
    return test()->post('/connexion', ['telephone' => '77 888 99 00', 'password' => $motDePasse]);
}

it('bloque le compte après 5 mots de passe faux, même avec le bon ensuite', function () {
    foreach (range(1, 5) as $essai) {
        tenterConnexion('mauvais');
    }

    tenterConnexion('lebonmotdepasse')->assertSessionHasErrors('telephone');
    $this->assertGuest();
});

it('remet le compteur à zéro après une connexion réussie', function () {
    foreach (range(1, 3) as $essai) {
        tenterConnexion('mauvais');
    }
    tenterConnexion('lebonmotdepasse');
    $this->post('/deconnexion');

    foreach (range(1, 3) as $essai) {
        tenterConnexion('mauvais');
    }

    tenterConnexion('lebonmotdepasse');
    $this->assertAuthenticatedAs($this->user);
});

it('alerte le super administrateur au moment du blocage', function () {
    foreach (range(1, 5) as $essai) {
        tenterConnexion('mauvais');
    }

    expect(DB::table('notifications')->where('notifiable_id', $this->super->id)->count())->toBe(1);
});

it('chiffre le secret de double authentification', function () {
    $this->super->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $brut = DB::table('users')->where('id', $this->super->id)->value('app_authentication_secret');

    expect($brut)->not->toBe('JBSWY3DPEHPK3PXP')
        ->and($this->super->fresh()->getAppAuthenticationSecret())->toBe('JBSWY3DPEHPK3PXP');
});

it('ouvre la page de profil du panneau, où se règle la double authentification', function () {
    $this->actingAs($this->super)->get('/admin/profile')->assertOk();
});
