<?php

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

it('ouvre les écrans du registre des membres au Secrétaire général', function (string $adresse) {
    $admin = User::factory()->create();
    $admin->assignRole('secretaire_general');

    $this->actingAs($admin)->get($adresse)->assertOk();
})->with(['/admin/membres', '/admin/membres/create']);

it('réserve les données de référence au super administrateur', function (string $adresse) {
    $secretaire = User::factory()->create();
    $secretaire->assignRole('secretaire_general');
    $super = User::factory()->create();
    $super->assignRole('super_administrateur');

    $this->actingAs($secretaire)->get($adresse)->assertForbidden();
    $this->flushSession();
    $this->actingAs($super)->get($adresse)->assertOk();
})->with(['/admin/cellules', '/admin/exercices', '/admin/tarifs', '/admin/postes', '/admin/commissions']);

it('refuse le panneau à un simple membre', function () {
    $utilisateur = User::factory()->create();
    $utilisateur->assignRole('membre');

    $this->actingAs($utilisateur)->get('/admin')->assertForbidden();
});
