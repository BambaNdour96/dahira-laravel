<?php

use App\Enums\NiveauEtudes;
use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Membre;
use App\Models\User;
use App\Services\StatutMembreService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Storage::fake('local');
    $this->cellule = Cellule::factory()->create();
    $this->commission = Commission::factory()->create();
});

function donneesInscription(array $surcharge = []): array
{
    return array_merge([
        'prenom' => 'Awa',
        'nom' => 'Fall',
        'sexe' => 'F',
        'adresse' => 'Médina Baye',
        'ville' => 'Kaolack',
        'pays_residence' => 'Sénégal',
        'date_naissance' => '1995-04-12',
        'telephone' => '77 123 45 67',
        'email' => '',
        'cellule_id' => test()->cellule->id,
        'commissions' => [test()->commission->id],
        'photo' => UploadedFile::fake()->image('photo.jpg', 1000, 1200),
        'password' => 'motdepasse',
        'password_confirmation' => 'motdepasse',
        'consentement' => '1',
    ], $surcharge);
}

it('affiche l\'accueil et le formulaire d\'inscription', function () {
    $this->get('/')->assertOk()->assertSee('Bienvenue dans la plateforme');
    $this->get('/inscription')->assertOk()->assertSee('Devenir membre');
});

it('inscrit un membre en attente, avec sa photo, et le connecte', function () {
    $this->post('/inscription', donneesInscription())->assertRedirect(route('inscription.merci'));

    $membre = Membre::query()->firstOrFail();

    expect($membre->statut)->toBe(StatutMembre::EnAttente)
        ->and($membre->telephone)->toBe('+221771234567')
        ->and($membre->consentement_le)->not->toBeNull()
        ->and($membre->photo_path)->toEndWith('.jpg')
        ->and($membre->user->hasRole('membre'))->toBeTrue();

    $this->assertAuthenticatedAs($membre->user);
});

it('exige le consentement', function () {
    $this->post('/inscription', donneesInscription(['consentement' => '']))
        ->assertSessionHasErrors('consentement');

    expect(Membre::query()->count())->toBe(0);
});

it('refuse un téléphone déjà inscrit, quel que soit son format', function () {
    Membre::factory()->create(['telephone' => '+221771234567']);

    $this->post('/inscription', donneesInscription(['telephone' => '00221 77 123 45 67']))
        ->assertSessionHasErrors('telephone');
});

it('connecte un membre par son téléphone', function () {
    $user = User::factory()->create(['telephone' => '+221771234567', 'password' => 'secret123']);
    $user->assignRole('membre');

    $this->post('/connexion', ['telephone' => '77 123 45 67', 'password' => 'secret123'])
        ->assertRedirect(route('espace'));

    $this->assertAuthenticatedAs($user);
});

it('refuse la connexion d\'un compte désactivé', function () {
    User::factory()->create(['telephone' => '+221771234567', 'password' => 'secret123', 'actif' => false]);

    $this->post('/connexion', ['telephone' => '771234567', 'password' => 'secret123'])
        ->assertSessionHasErrors('telephone');

    $this->assertGuest();
});

it('permet de corriger une fiche et la renvoie en attente', function () {
    $user = User::factory()->create();
    $user->assignRole('membre');
    $membre = Membre::factory()->for($user)->for($this->cellule)->create();
    app(StatutMembreService::class)->changer($membre, StatutMembre::ACorriger, null, 'Photo floue');

    $this->actingAs($user)->get('/mon-espace')->assertSee('Photo floue');

    $this->actingAs($user)->put('/mon-espace/profil', [
        'prenom' => 'Awa',
        'nom' => 'Fall',
        'sexe' => 'F',
        'adresse' => 'Médina Baye',
        'ville' => 'Kaolack',
        'pays_residence' => 'Sénégal',
        'cellule_id' => $this->cellule->id,
        'date_naissance' => '1995-04-12',
        'photo' => UploadedFile::fake()->image('nette.jpg', 900, 900),
    ])->assertRedirect(route('espace'));

    expect($membre->refresh()->statut)->toBe(StatutMembre::EnAttente);
});

it('protège les photos des membres', function () {
    $proprietaire = User::factory()->create();
    $membre = Membre::factory()->for($proprietaire)->create([
        'photo_path' => UploadedFile::fake()->image('p.jpg', 900, 900)->store('photos', 'local'),
    ]);
    $autre = User::factory()->create();
    $autre->assignRole('membre');

    $this->actingAs($proprietaire)->get(route('photos.show', $membre))->assertOk();
    $this->actingAs($autre)->get(route('photos.show', $membre))->assertForbidden();
});

it('refuse une année de naissance invalide', function (string $date) {
    $this->post('/inscription', donneesInscription(['date_naissance' => $date]))
        ->assertSessionHasErrors('date_naissance');
})->with(['19955-04-12', '1850-01-01', '2999-01-01']);

it('enregistre le lieu de naissance, facultatif', function () {
    $this->post('/inscription', donneesInscription(['lieu_naissance' => 'Diourbel']))
        ->assertRedirect(route('inscription.merci'));

    expect(Membre::query()->firstOrFail()->lieu_naissance)->toBe('Diourbel');
});

it('ne garde ni compte ni mot de passe quand l\'inscription est refusée', function () {
    $this->post('/inscription', donneesInscription(['consentement' => '', 'password' => 'secretJamaisGarde']))
        ->assertSessionHasErrors('consentement')
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation');

    expect(User::query()->count())->toBe(0)
        ->and(Membre::query()->count())->toBe(0);

    $this->assertGuest();
});

it('affiche les affectations et la barre de progression du profil', function () {
    $user = User::factory()->create();
    $user->assignRole('membre');
    Membre::factory()->for($user)->for($this->cellule)->valide()->create(['email' => null]);

    $this->actingAs($user)->get('/mon-espace')
        ->assertOk()
        ->assertSee('Mes affectations')
        ->assertSee($this->cellule->nom)
        ->assertSee('Compléter mon profil');
});

it('permet de compléter la formation et la situation professionnelle', function () {
    $user = User::factory()->create();
    $user->assignRole('membre');
    $membre = Membre::factory()->for($user)->for($this->cellule)->valide()->create([
        'niveau_etudes' => null,
        'diplome' => null,
        'situation_professionnelle' => null,
        'profession' => null,
    ]);

    $this->actingAs($user)->put('/mon-espace/profil', [
        'adresse' => 'Médina Baye',
        'ville' => 'Kaolack',
        'pays_residence' => 'Sénégal',
        'niveau_etudes' => 'superieur',
        'diplome' => 'Master',
        'langues' => ['wolof', 'arabe'],
        'situation_professionnelle' => 'salarie',
        'profession' => 'Comptable',
    ])->assertRedirect(route('espace'));

    $membre->refresh();

    expect($membre->niveau_etudes)->toBe(NiveauEtudes::Superieur)
        ->and($membre->langues)->toBe(['wolof', 'arabe'])
        ->and($membre->profession)->toBe('Comptable');
});

it('limite le compte d\'un membre en attente au suivi de sa demande', function () {
    $user = User::factory()->create();
    $user->assignRole('membre');
    Membre::factory()->for($user)->for($this->cellule)->create();

    $this->actingAs($user)->get('/mon-espace')
        ->assertOk()
        ->assertSee('en cours de vérification')
        ->assertDontSee('Compléter mon profil')
        ->assertDontSee('Mes affectations');

    $this->actingAs($user)->get('/mon-espace/profil')->assertForbidden();
});

it('explique clairement à un membre qu\'il n\'a pas accès au panneau', function () {
    $user = User::factory()->create(['name' => 'Awa Fall']);
    $user->assignRole('membre');

    $this->actingAs($user)->get('/admin')
        ->assertForbidden()
        ->assertSee('Accès non autorisé')
        ->assertSee('Changer de compte');
});
