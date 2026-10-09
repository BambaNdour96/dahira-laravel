<?php

use App\Models\Membre;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->compte = User::factory()->create();
    $this->compte->assignRole('membre');
    Membre::factory()->for($this->compte)->valide()->create();
});

it('prépare l\'activation de l\'empreinte pour un compte connecté', function () {
    $this->actingAs($this->compte)->get(route('empreinte.options'))
        ->assertOk()
        ->assertJsonStructure(['challenge', 'rp', 'user']);
});

it('refuse l\'activation sans préparation préalable', function () {
    $this->actingAs($this->compte)->postJson(route('empreinte.enregistrer'), ['cle' => '{}'])
        ->assertStatus(422);
});

it('prépare une connexion par empreinte', function () {
    $this->get(route('empreinte.options-connexion'))
        ->assertOk()
        ->assertJsonStructure(['challenge']);
});

it('refuse une empreinte invalide et ne connecte personne', function () {
    $this->get(route('empreinte.options-connexion'));

    $this->postJson(route('empreinte.connexion'), ['reponse' => '{"id":"faux"}'])
        ->assertStatus(422)
        ->assertJson(['message' => 'Empreinte non reconnue. Connectez-vous avec votre mot de passe.']);

    $this->assertGuest();
});

it('propose l\'empreinte dans les Paramètres et sur la page de connexion', function () {
    $this->actingAs($this->compte)->get(route('parametres'))
        ->assertSee('Connexion par empreinte')
        ->assertSee('data-activer-empreinte', false);

    auth()->logout();

    $this->get(route('login'))->assertSee('Se connecter avec l', false);
});
