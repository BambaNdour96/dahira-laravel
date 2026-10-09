<?php

use App\Listeners\MemoriserNumero;
use App\Models\Membre;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->compte = User::factory()->create(['telephone' => '+221771234567', 'password' => 'motdepasse1']);
    $this->compte->assignRole('membre');
    Membre::factory()->for($this->compte)->valide()->create();
});

it('mémorise le numéro après une connexion réussie', function () {
    $this->post('/connexion', ['telephone' => '77 123 45 67', 'password' => 'motdepasse1'])
        ->assertCookie(MemoriserNumero::COOKIE, '+221771234567');
});

it('ne montre que les 3 derniers chiffres du numéro mémorisé', function () {
    $this->withCookie(MemoriserNumero::COOKIE, '+221771234567')
        ->get('/connexion')
        ->assertOk()
        ->assertSee('****567')
        ->assertSee('Utiliser un autre numéro')
        ->assertDontSee('771234567');
});

it('connecte avec le numéro mémorisé et le seul mot de passe', function () {
    $this->withCookie(MemoriserNumero::COOKIE, '+221771234567')
        ->post('/connexion', ['numero_memorise' => '1', 'password' => 'motdepasse1']);

    $this->assertAuthenticatedAs($this->compte);
});

it('refuse un mauvais mot de passe, même avec le numéro mémorisé', function () {
    $this->withCookie(MemoriserNumero::COOKIE, '+221771234567')
        ->post('/connexion', ['numero_memorise' => '1', 'password' => 'mauvais1']);

    $this->assertGuest();
});

it('oublie le numéro avec « Utiliser un autre numéro »', function () {
    $this->withCookie(MemoriserNumero::COOKIE, '+221771234567')
        ->get('/connexion/autre-numero')
        ->assertRedirect(route('login'))
        ->assertCookieExpired(MemoriserNumero::COOKIE);
});
