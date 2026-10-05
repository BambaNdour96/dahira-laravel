<?php

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->responsable = User::factory()->create();
    $this->responsable->assignRole('responsable_section');

    $this->sectionA = Section::factory()->create(['responsable_id' => $this->responsable->id]);
    $this->sectionB = Section::factory()->create();

    $this->membreA = Membre::factory()->for($this->sectionA)->create();
    $this->membreB = Membre::factory()->for($this->sectionB)->create();
});

it('montre au responsable les membres de sa section uniquement', function () {
    $this->actingAs($this->responsable);

    $this->get("/admin/membres/{$this->membreA->id}/edit")->assertOk();

    expect($this->get("/admin/membres/{$this->membreB->id}/edit")->status())->toBeIn([403, 404]);
});

it('interdit au responsable les écrans réservés à l\'administrateur', function (string $adresse) {
    $this->actingAs($this->responsable)->get($adresse)->assertForbidden();
})->with(['/admin/sections', '/admin/exercices', '/admin/tarifs']);

it('interdit au trésorier de modifier un membre', function () {
    $tresorier = User::factory()->create();
    $tresorier->assignRole('tresorier');

    $this->actingAs($tresorier)->get('/admin/membres')->assertOk();
    $this->actingAs($tresorier)->get("/admin/membres/{$this->membreA->id}/edit")->assertForbidden();
});

it('laisse le responsable valider une inscription de sa section', function () {
    expect(Gate::forUser($this->responsable)->allows('changerStatut', [$this->membreA, StatutMembre::Valide]))->toBeTrue()
        ->and(Gate::forUser($this->responsable)->allows('changerStatut', [$this->membreB, StatutMembre::Valide]))->toBeFalse()
        ->and(Gate::forUser($this->responsable)->allows('changerStatut', [$this->membreA, StatutMembre::Radie]))->toBeFalse();
});

it('refuse le panneau à un compte désactivé', function () {
    $admin = User::factory()->create(['actif' => false]);
    $admin->assignRole('administrateur');

    $this->actingAs($admin)->get('/admin')->assertForbidden();
});
