<?php

use App\Models\Parametre;
use App\Models\User;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, ParametresSeeder::class]);
    Parametre::query()->where('cle', 'maintenance')->update(['valeur' => '1']);
});

it('affiche la page de maintenance aux visiteurs et aux membres', function () {
    $this->get('/')->assertStatus(503)->assertSee('Maintenance en cours');

    $membre = User::factory()->create();
    $membre->assignRole('membre');
    $this->actingAs($membre)->get('/mon-espace')->assertStatus(503);
});

it('laisse la vérification des cartes accessible', function () {
    $this->get('/v/'.str_repeat('a', 22))->assertOk()->assertSee('Carte non valide');
});

it('laisse passer le super administrateur', function () {
    $super = User::factory()->create();
    $super->assignRole(['super_administrateur', 'administrateur']);

    $this->actingAs($super)->get('/')->assertOk();
});

it('rouvre le site quand la maintenance est désactivée', function () {
    Parametre::query()->where('cle', 'maintenance')->update(['valeur' => '0']);

    $this->get('/')->assertOk();
});
