<?php

use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Membre;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->responsable = User::factory()->create();
    $this->responsable->assignRole('jewrine_cellule');

    $this->celluleA = celluleDirigeePar($this->responsable);
    $this->celluleB = Cellule::factory()->create();

    $this->membreA = Membre::factory()->for($this->celluleA)->create();
    $this->membreB = Membre::factory()->for($this->celluleB)->create();
});

it('montre au responsable les membres de sa cellule uniquement', function () {
    $this->actingAs($this->responsable);

    $this->get("/admin/membres/{$this->membreA->id}/edit")->assertOk();

    expect($this->get("/admin/membres/{$this->membreB->id}/edit")->status())->toBeIn([403, 404]);
});

it('interdit au responsable les écrans réservés à l\'administrateur', function (string $adresse) {
    $this->actingAs($this->responsable)->get($adresse)->assertForbidden();
})->with(['/admin/cellules', '/admin/exercices', '/admin/tarifs']);

it('interdit au trésorier de modifier un membre', function () {
    $tresorier = User::factory()->create();
    $tresorier->assignRole('jewrine_finance');

    $this->actingAs($tresorier)->get('/admin/membres')->assertOk();
    $this->actingAs($tresorier)->get("/admin/membres/{$this->membreA->id}/edit")->assertForbidden();
});

it('laisse le responsable valider une inscription de sa cellule', function () {
    expect(Gate::forUser($this->responsable)->allows('changerStatut', [$this->membreA, StatutMembre::Accepte]))->toBeTrue()
        ->and(Gate::forUser($this->responsable)->allows('changerStatut', [$this->membreB, StatutMembre::Accepte]))->toBeFalse()
        ->and(Gate::forUser($this->responsable)->allows('changerStatut', [$this->membreA, StatutMembre::Radie]))->toBeFalse();
});

it('refuse le panneau à un compte désactivé', function () {
    $admin = User::factory()->create(['actif' => false]);
    $admin->assignRole('secretaire_general');

    $this->actingAs($admin)->get('/admin')->assertForbidden();
});
