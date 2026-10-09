<?php

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->compte = User::factory()->create();
    $this->compte->assignRole('membre');
});

it('présente un accueil centré sur la carte et les cotisations au membre validé', function () {
    Membre::factory()->for($this->compte)->valide()->create(['matricule' => 'NDK-2026-0099']);

    $this->actingAs($this->compte)->get('/mon-espace')
        ->assertOk()
        ->assertSee('Assalamou aleykoum')
        ->assertSee('Ma carte de membre')
        ->assertSee('NDK-2026-0099')
        ->assertSee('Cotisations à jour')
        ->assertSee('Mes reçus')
        ->assertDontSee('Modifier mon profil</a>', false);
});

it('montre le suivi de la demande, étape par étape, au membre pas encore validé', function () {
    Membre::factory()->for($this->compte)->create(['statut' => StatutMembre::EnAttente]);

    $this->actingAs($this->compte)->get('/mon-espace')
        ->assertOk()
        ->assertSee('Demande envoyée')
        ->assertSee('Membre validé')
        ->assertSee('en cours de vérification')
        ->assertDontSee('Ma carte de membre');
});

it('regroupe les réglages dans la page Paramètres, accessible depuis le menu', function () {
    Membre::factory()->for($this->compte)->valide()->create();

    $this->actingAs($this->compte)->get('/mon-espace')->assertSee(route('parametres'), false);

    $this->actingAs($this->compte)->get('/mon-compte/parametres')
        ->assertOk()
        ->assertSee('Modifier mon profil et ma photo')
        ->assertSee('Changer mon mot de passe')
        ->assertSee('Télécharger mes données')
        ->assertSee('Supprimer mon compte')
        ->assertSee('Se déconnecter');
});
