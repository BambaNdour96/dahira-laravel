<?php

use App\Models\Cellule;
use App\Models\Commission;
use App\Models\Membre;
use App\Models\Parametre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CarteService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\ReglesValidationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Tests dans un vrai navigateur (Chrome) : pages, JavaScript, affichage sur téléphone.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    // Dans un vrai navigateur, on charge le vrai style et le vrai JavaScript.
    $this->withVite();

    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class, ReglesValidationSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);

    Cellule::factory()->create(['nom' => 'Kaolack']);
    Commission::factory()->create(['nom' => 'Organisation']);

    $this->compte = User::factory()->create(['password' => 'motdepasse1']);
    $this->compte->assignRole('membre');
    $this->membre = Membre::factory()->for($this->compte)->valide()->create(['valide_le' => now()]);
    $this->carte = app(CarteService::class)->emettre($this->membre);

    $this->super = User::factory()->create();
    $this->super->assignRole(['super_administrateur', 'administrateur']);
});

it('ouvre toutes les pages publiques sans erreur dans la console', function () {
    visit(['/', '/inscription', '/connexion', '/mot-de-passe-oublie', '/confidentialite', '/hors-ligne'])
        ->assertNoJavascriptErrors();
});

it('ouvre la page de vérification d\'une carte sans erreur', function () {
    visit('/v/'.$this->carte->jeton)
        ->assertSee('Membre actif')
        ->assertNoJavascriptErrors();
});

it('ouvre toutes les pages du membre sans erreur dans la console', function () {
    $this->actingAs($this->compte);

    visit([
        '/mon-espace', '/ma-carte', '/mes-cotisations', '/mes-recus', '/mes-notifications',
        '/evenements', '/mon-compte/mot-de-passe', '/mon-compte/suppression',
    ])->assertNoJavascriptErrors();
});

it('change le montant du bouton Wave selon le nombre de mois choisi', function () {
    foreach ([1 => 'UNMOIS', 2 => 'DEUXMOIS', 3 => 'TROISMOIS'] as $nombre => $code) {
        Parametre::query()->where('cle', 'wave_lien_'.$nombre.'_mois')->update(['valeur' => 'https://pay.wave.com/m/'.$code]);
    }

    $this->actingAs($this->compte);

    visit('/mes-cotisations')
        ->assertSee('Payer 1 000 FCFA avec Wave')
        ->click('2 mois')
        ->assertSee('Payer 2 000 FCFA avec Wave')
        ->click('3 mois')
        ->assertSee('Payer 3 000 FCFA avec Wave')
        ->assertNoJavascriptErrors();
});

it('fait avancer l\'inscription avec le bouton Continuer, sans erreur', function () {
    visit('/inscription')
        ->assertSee('Continuer')
        ->click('Continuer')
        ->assertNoJavascriptErrors();
});

it('n\'oblige jamais à faire défiler la page de côté sur un téléphone', function (string $adresse, bool $connecte) {
    if ($connecte) {
        $this->actingAs($this->compte);
    }

    $page = visit($adresse)->on()->mobile();

    $mesure = $page->script('JSON.stringify({ origine: location.origin, styles: [...document.querySelectorAll("link[rel=stylesheet]")].map((l) => l.href + (l.sheet ? " CHARGEE" : " NON CHARGEE")), ecran: document.documentElement.clientWidth, page: document.documentElement.scrollWidth, depassent: [...document.querySelectorAll("body *")].filter((e) => !e.closest("[aria-hidden=true]") && e.getBoundingClientRect().right > document.documentElement.clientWidth + 1).slice(0, 6).map((e) => e.tagName.toLowerCase() + " " + String(e.className).slice(0, 70) + " => " + Math.round(e.getBoundingClientRect().right)) })');

    expect($mesure)->toContain('"depassent":[]');
})->with([
    'accueil' => ['/', false],
    'inscription' => ['/inscription', false],
    'connexion' => ['/connexion', false],
    'mon compte' => ['/mon-espace', true],
    'ma carte' => ['/ma-carte', true],
    'mes cotisations' => ['/mes-cotisations', true],
]);

it('ouvre les écrans du panneau sans erreur dans la console', function () {
    $this->actingAs($this->super);

    visit([
        '/admin', '/admin/membres', '/admin/paiements', '/admin/relances', '/admin/evenements',
        '/admin/validation-bureau', '/admin/connectes', '/admin/demandes-suppression',
    ])->assertNoJavascriptErrors();
});

it('suit le thème sombre du téléphone, sans erreur', function () {
    $page = visit('/')->inDarkMode();

    expect($page->script('document.documentElement.classList.contains("dark")'))->toBeTrue()
        ->and($page->script('getComputedStyle(document.body).backgroundColor'))->toBe('rgb(12, 18, 16)');

    $page->assertNoJavascriptErrors();
});

it('laisse le membre choisir le thème avec le bouton', function () {
    visit('/connexion')
        ->assertSee('Thème : automatique')
        ->click('#bouton-theme')
        ->assertSee('Thème : clair')
        ->click('#bouton-theme')
        ->assertSee('Thème : sombre')
        ->assertNoJavascriptErrors();
});

it('déploie le tiroir des notifications, puis le replie en touchant la page', function () {
    $this->compte->notify(new NotificationMembre('Avis de test', 'Contenu du test.'));
    $this->actingAs($this->compte);

    $page = visit('/mon-espace');
    $ferme = 'document.getElementById("tiroir-notifications").classList.contains("translate-x-full")';

    expect($page->script($ferme))->toBeTrue();

    $page->click('#ouvrir-notifications')->assertSee('Avis de test');
    expect($page->script($ferme))->toBeFalse();

    $page->click('#voile-notifications');
    expect($page->script($ferme))->toBeTrue();

    $page->assertNoJavascriptErrors();
});
