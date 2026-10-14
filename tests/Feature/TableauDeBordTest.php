<?php

use App\Filament\Widgets\InscriptionsParMois;
use App\Filament\Widgets\MembresParCellule;
use App\Filament\Widgets\MembresParCommission;
use App\Filament\Widgets\NiveauxEtudes;
use App\Filament\Widgets\RepartitionSexe;
use App\Filament\Widgets\SituationsProfessionnelles;
use App\Filament\Widgets\StatistiquesMembres;
use App\Filament\Widgets\TranchesAge;
use App\Models\Membre;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('secretaire_general');
});

it('ouvre le tableau de bord à l\'administrateur', function () {
    $this->actingAs($this->admin)->get('/admin')->assertOk();
});

it('compte les inscriptions à valider', function () {
    Membre::factory()->count(3)->create();
    Membre::factory()->count(2)->valide()->create();

    $this->actingAs($this->admin);

    Livewire::test(StatistiquesMembres::class)
        ->assertSee('Inscriptions à valider')
        ->assertSee('sur 5 inscrits');
});

it('limite les compteurs du Jewrine à sa cellule', function () {
    $jewrine = User::factory()->create();
    $jewrine->assignRole('jewrine_cellule');
    $cellule = celluleDirigeePar($jewrine);

    Membre::factory()->count(2)->for($cellule)->create();
    Membre::factory()->count(4)->create();

    $this->actingAs($jewrine);

    Livewire::test(StatistiquesMembres::class)->assertSee('sur 2 inscrits');
});

it('affiche tous les graphiques sans erreur', function (string $graphique) {
    Membre::factory()->count(3)->valide()->create();

    $this->actingAs($this->admin);

    Livewire::test($graphique)->assertHasNoErrors();
})->with([
    InscriptionsParMois::class,
    MembresParCellule::class,
    MembresParCommission::class,
    RepartitionSexe::class,
    TranchesAge::class,
    NiveauxEtudes::class,
    SituationsProfessionnelles::class,
]);
