<?php

use App\Models\Membre;
use App\Models\Poste;
use App\Models\User;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, PostesSeeder::class]);
});

it('crée la liste officielle des postes, dans l\'ordre', function () {
    expect(Poste::query()->orderBy('ordre')->pluck('nom')->first())->toBe('Jewrine national')
        ->and(Poste::query()->count())->toBe(8);
});

it('réserve l\'écran des postes à l\'administrateur', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrateur');
    $jewrine = User::factory()->create();
    $jewrine->assignRole('responsable_section');

    $this->actingAs($admin)->get('/admin/postes')->assertOk();
    $this->actingAs($jewrine)->get('/admin/postes')->assertForbidden();
});

it('calcule le pourcentage de profil complété', function () {
    $vide = Membre::factory()->create(array_fill_keys(Membre::CHAMPS_PROFIL, null));
    $complet = Membre::factory()->create([
        'email' => 'awa@exemple.sn',
        'diplome' => 'Licence',
        'domaine_etudes' => 'Gestion',
        'secteur_activite' => 'Commerce',
    ]);

    expect($vide->completude())->toBe(0)
        ->and($complet->completude())->toBe(100);
});
