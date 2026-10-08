<?php

use App\Models\Membre;
use App\Models\User;
use App\Services\CarteService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('décrit l\'application installable avec ses icônes', function () {
    $fiche = json_decode((string) file_get_contents(public_path('manifest.json')), true);

    expect($fiche)->toBeArray()
        ->and($fiche['name'])->toBe('Daara Nouroud Daareyni Kaolack')
        ->and($fiche['display'])->toBe('standalone')
        ->and($fiche['start_url'])->toBe('/mon-espace');

    foreach ($fiche['icons'] as $icone) {
        expect(file_exists(public_path(ltrim($icone['src'], '/'))))->toBeTrue("Icône manquante : {$icone['src']}");
    }
});

it('relie chaque page à l\'application', function () {
    $this->get('/')
        ->assertSee('<link rel="manifest" href="/manifest.json">', false)
        ->assertSee('<meta name="theme-color" content="#1e5223">', false)
        ->assertSee('serviceWorker', false);
});

it('affiche une page claire hors connexion', function () {
    $this->get('/hors-ligne')->assertOk()->assertSee('Vous êtes hors connexion');
});

it('autorise le service worker dans la politique de sécurité', function () {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("worker-src 'self'")
        ->and($csp)->toContain("manifest-src 'self'");
});

it('ne garde que la carte, à part, et aucune autre page personnelle', function () {
    $programme = (string) file_get_contents(public_path('sw.js'));

    expect($programme)->toContain("CACHE_CARTE = 'ndk-carte'")
        ->and($programme)->toContain('/ma-carte')
        ->and($programme)->not->toContain('/mon-espace')
        ->and($programme)->not->toContain('/mes-cotisations')
        ->and($programme)->not->toContain('/mes-recus');
});

it('efface la copie de la carte quand personne n\'est connecté', function () {
    $this->get('/')
        ->assertSee("caches.delete('ndk-carte')", false)
        ->assertSee('const proprietaire = null;', false);
});

it('propose la carte depuis la page hors connexion', function () {
    $this->get('/hors-ligne')->assertSee('Afficher ma carte de membre');
});

it('indique sur la carte qu\'elle reste consultable sans connexion', function () {
    $this->seed(RolesSeeder::class);

    $compte = User::factory()->create();
    $compte->assignRole('membre');
    $membre = Membre::factory()->for($compte)->valide()->create();
    app(CarteService::class)->emettre($membre);

    $this->actingAs($compte)->get('/ma-carte')
        ->assertOk()
        ->assertSee('reste consultable sans connexion');
});
