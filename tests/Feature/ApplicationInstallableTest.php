<?php

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

it('ne met en mémoire aucune page personnelle', function () {
    $programme = (string) file_get_contents(public_path('sw.js'));

    expect($programme)->toContain('/hors-ligne')
        ->and($programme)->not->toContain('/mon-espace')
        ->and($programme)->not->toContain('/ma-carte');
});

it('autorise le service worker dans la politique de sécurité', function () {
    $csp = (string) $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("worker-src 'self'")
        ->and($csp)->toContain("manifest-src 'self'");
});
