<?php

use App\Models\Membre;
use App\Models\User;
use App\Notifications\ConfirmerEmail;
use App\Notifications\NotificationMembre;
use App\Rules\EmailFiable;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesSeeder::class));

it('refuse les adresses mal formées, jetables ou déjà prises, même en majuscules', function () {
    User::factory()->create(['email' => 'awa@exemple.sn']);
    $refuse = fn (string $email): bool => Validator::make(['email' => $email], ['email' => [new EmailFiable]])->fails();

    expect($refuse('awa@gmail'))->toBeTrue()
        ->and($refuse('moussa@yopmail.com'))->toBeTrue()
        ->and($refuse('AWA@Exemple.sn'))->toBeTrue()
        ->and($refuse('fatou@exemple.sn'))->toBeFalse();
});

it('laisse un membre garder sa propre adresse dans son profil', function () {
    $compte = User::factory()->create(['email' => 'awa@exemple.sn']);

    expect(Validator::make(['email' => 'awa@exemple.sn'], ['email' => [new EmailFiable($compte->id)]])->fails())->toBeFalse();
});

it('enregistre les adresses en minuscules et demande de confirmer une nouvelle adresse', function () {
    Notification::fake();

    $compte = User::factory()->unverified()->create(['email' => 'Awa@Exemple.SN']);

    expect($compte->email)->toBe('awa@exemple.sn');
    Notification::assertSentTo($compte, ConfirmerEmail::class);
});

it('redemande une confirmation quand l\'adresse change', function () {
    Notification::fake();
    $compte = User::factory()->create(['email' => 'awa@exemple.sn']);

    $compte->update(['email' => 'awa.nouvelle@exemple.sn']);

    expect($compte->fresh()->email_verified_at)->toBeNull();
    Notification::assertSentTo($compte, ConfirmerEmail::class);
});

it('confirme l\'adresse avec le lien signé, et refuse un lien trafiqué', function () {
    $compte = User::factory()->unverified()->create(['email' => 'awa@exemple.sn']);

    $faux = URL::temporarySignedRoute('email.confirmer', now()->addDay(), ['user' => $compte->id, 'hash' => sha1('autre@exemple.sn')]);
    $this->get($faux)->assertForbidden();

    $vrai = URL::temporarySignedRoute('email.confirmer', now()->addDay(), ['user' => $compte->id, 'hash' => sha1('awa@exemple.sn')]);
    $this->get($vrai)->assertRedirect(route('login'));

    expect($compte->fresh()->email_verified_at)->not->toBeNull();
});

it('n\'envoie rien d\'important à une adresse non confirmée', function () {
    Notification::fake();
    $compte = User::factory()->unverified()->create(['telephone' => '+221770009999', 'email' => 'awa@exemple.sn']);
    $compte->assignRole('membre');

    expect((new NotificationMembre('Titre', 'Message'))->via($compte))->not->toContain('mail');

    $this->post('/mot-de-passe-oublie', ['telephone' => '77 000 99 99']);
    Notification::assertNotSentTo($compte, ResetPassword::class);
});

it('affiche le cadre de confirmation sur l\'accueil, avec le bouton pour renvoyer le lien', function () {
    Notification::fake();
    $compte = User::factory()->unverified()->create(['email' => 'awa@exemple.sn']);
    $compte->assignRole('membre');
    Membre::factory()->for($compte)->valide()->create();

    $this->actingAs($compte)->get('/mon-espace')
        ->assertSee('Confirmez votre adresse email')
        ->assertSee('Renvoyer le lien');

    $this->actingAs($compte)->post(route('email.renvoyer'))->assertRedirect();
    Notification::assertSentTo($compte, ConfirmerEmail::class);
});
