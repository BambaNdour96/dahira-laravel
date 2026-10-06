<?php

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\User;
use App\Services\CarteService;
use App\Services\StatutMembreService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

function membreValideAvecCarte(?User $user = null): Membre
{
    $membre = Membre::factory()->when($user, fn ($fabrique) => $fabrique->for($user))->create();
    app(StatutMembreService::class)->changer($membre, StatutMembre::Valide, null);

    return $membre->refresh();
}

it('émet une carte active à la validation', function () {
    $membre = membreValideAvecCarte();

    expect($membre->carteActive)->not->toBeNull()
        ->and(strlen($membre->carteActive->jeton))->toBe(22);
});

it('affiche « Membre actif » au scan, sans données sensibles', function () {
    $membre = membreValideAvecCarte();

    $this->get('/v/'.$membre->carteActive->jeton)
        ->assertOk()
        ->assertSee('Membre actif')
        ->assertSee($membre->matricule)
        ->assertDontSee($membre->telephone);
});

it('affiche « Carte non valide » pour un jeton inconnu', function () {
    $this->get('/v/'.str_repeat('a', 22))->assertOk()->assertSee('Carte non valide');
});

it('invalide l\'ancienne carte après une révocation', function () {
    $membre = membreValideAvecCarte();
    $ancien = $membre->carteActive->jeton;

    $nouvelle = app(CarteService::class)->revoquer($membre->carteActive, null, 'Carte perdue');

    $this->get('/v/'.$ancien)->assertSee('Carte non valide');
    $this->get('/v/'.$nouvelle->jeton)->assertSee('Membre actif');
});

it('invalide la carte d\'un membre suspendu', function () {
    $membre = membreValideAvecCarte();
    app(StatutMembreService::class)->changer($membre, StatutMembre::Suspendu, null, 'Absences répétées');

    $this->get('/v/'.$membre->carteActive->jeton)->assertSee('Carte non valide');
});

it('montre la carte au membre validé et la refuse à un membre en attente', function () {
    $user = User::factory()->create();
    $user->assignRole('membre');
    $membre = Membre::factory()->for($user)->create();

    $this->actingAs($user)->get('/ma-carte')->assertNotFound();

    app(StatutMembreService::class)->changer($membre, StatutMembre::Valide, null);

    $this->actingAs($user)->get('/ma-carte')
        ->assertOk()
        ->assertSee($membre->refresh()->matricule);
});

it('protège la photo de la page de vérification par une signature', function () {
    $membre = membreValideAvecCarte();

    $this->get('/v/'.$membre->carteActive->jeton.'/photo')->assertForbidden();
});
