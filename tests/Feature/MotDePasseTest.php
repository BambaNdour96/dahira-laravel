<?php

use App\Models\Membre;
use App\Models\User;
use App\Services\CompteBureauService;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->user = User::factory()->create([
        'telephone' => '+221774445566',
        'email' => 'awa@exemple.sn',
        'password' => 'ancienmotdepasse',
    ]);
    $this->user->assignRole('membre');
    Membre::factory()->for($this->user)->valide()->create();
});

it('permet de changer son mot de passe', function () {
    $this->actingAs($this->user)->put('/mon-compte/mot-de-passe', [
        'mot_de_passe_actuel' => 'ancienmotdepasse',
        'password' => 'nouveaumotdepasse',
        'password_confirmation' => 'nouveaumotdepasse',
    ])->assertRedirect(route('login'));

    expect(Hash::check('nouveaumotdepasse', $this->user->fresh()->password))->toBeTrue();
});

it('refuse un mot de passe actuel incorrect', function () {
    $this->actingAs($this->user)->put('/mon-compte/mot-de-passe', [
        'mot_de_passe_actuel' => 'mauvais',
        'password' => 'nouveaumotdepasse',
        'password_confirmation' => 'nouveaumotdepasse',
    ])->assertSessionHasErrors('mot_de_passe_actuel');
});

it('impose de changer un mot de passe provisoire', function () {
    $provisoire = app(CompteBureauService::class)->reinitialiserMotDePasse($this->user);
    $this->user->refresh();

    $this->actingAs($this->user)->get('/mon-espace')->assertRedirect(route('mot-de-passe.edit'));

    $this->actingAs($this->user)->put('/mon-compte/mot-de-passe', [
        'mot_de_passe_actuel' => $provisoire,
        'password' => 'monpropremotdepasse',
        'password_confirmation' => 'monpropremotdepasse',
    ]);

    expect((bool) $this->user->fresh()->getAttribute('doit_changer_mot_de_passe'))->toBeFalse();
    $this->actingAs($this->user->fresh())->get('/mon-espace')->assertOk();
});

it('impose aussi le changement dans le panneau du bureau', function () {
    $this->user->assignRole('tresorier');
    $this->user->forceFill(['doit_changer_mot_de_passe' => true])->save();

    $this->actingAs($this->user)->get('/admin')->assertRedirect(route('mot-de-passe.edit'));
});

it('envoie un lien par email au membre qui a une adresse', function () {
    Notification::fake();

    $this->post('/mot-de-passe-oublie', ['telephone' => '77 444 55 66'])->assertSessionHas('succes');

    Notification::assertSentTo($this->user, ResetPassword::class);
});

it('n\'envoie rien sans adresse email, avec le même message', function () {
    Notification::fake();
    $this->user->forceFill(['email' => null])->save();

    $this->post('/mot-de-passe-oublie', ['telephone' => '77 444 55 66'])->assertSessionHas('succes');

    Notification::assertNothingSent();
});

it('change le mot de passe avec le lien reçu', function () {
    $token = Password::createToken($this->user);

    $this->post('/reinitialiser-mot-de-passe', [
        'token' => $token,
        'email' => 'awa@exemple.sn',
        'password' => 'motdepasseretrouve',
        'password_confirmation' => 'motdepasseretrouve',
    ])->assertRedirect(route('login'));

    expect(Hash::check('motdepasseretrouve', $this->user->fresh()->password))->toBeTrue();
});

it('déconnecte la personne après le changement de son mot de passe', function () {
    $this->actingAs($this->user)->put('/mon-compte/mot-de-passe', [
        'mot_de_passe_actuel' => 'ancienmotdepasse',
        'password' => 'nouveaumotdepasse',
        'password_confirmation' => 'nouveaumotdepasse',
    ]);

    $this->assertGuest();

    $this->post('/connexion', ['telephone' => '77 444 55 66', 'password' => 'ancienmotdepasse'])
        ->assertSessionHasErrors('telephone');
    $this->post('/connexion', ['telephone' => '77 444 55 66', 'password' => 'nouveaumotdepasse']);
    $this->assertAuthenticatedAs($this->user);
});

it('génère un mot de passe provisoire facile à lire, qui fonctionne et remplace l\'ancien', function () {
    $provisoire = app(CompteBureauService::class)->reinitialiserMotDePasse($this->user);

    expect($provisoire)->toMatch('/^[A-HJ-NP-Z2-9]{8}$/');

    $this->post('/connexion', ['telephone' => '77 444 55 66', 'password' => 'ancienmotdepasse'])
        ->assertSessionHasErrors('telephone');
    $this->post('/connexion', ['telephone' => '77 444 55 66', 'password' => $provisoire]);
    $this->assertAuthenticatedAs($this->user);
});
