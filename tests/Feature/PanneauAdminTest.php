<?php

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

it('ouvre les écrans du registre à un administrateur', function (string $adresse) {
    $admin = User::factory()->create();
    $admin->assignRole('secretaire_general');

    $this->actingAs($admin)->get($adresse)->assertOk();
})->with(['/admin/membres', '/admin/membres/create', '/admin/cellules', '/admin/exercices', '/admin/tarifs']);

it('refuse le panneau à un simple membre', function () {
    $utilisateur = User::factory()->create();
    $utilisateur->assignRole('membre');

    $this->actingAs($utilisateur)->get('/admin')->assertForbidden();
});
