<?php

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

it('envoie les en-têtes de sécurité sur le site des membres', function () {
    $reponse = $this->get('/');

    $reponse->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = (string) $reponse->headers->get('Content-Security-Policy');

    expect($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+'/");
});

it('signe les scripts de la page avec le nonce de la politique', function () {
    $reponse = $this->get('/inscription');

    preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $reponse->headers->get('Content-Security-Policy'), $resultat);

    expect($resultat)->toHaveKey(1);
    $reponse->assertSee('nonce="'.$resultat[1].'"', false);
});

it('exige HTTPS dans la durée une fois la connexion chiffrée', function () {
    $this->get('https://localhost/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('protège aussi le panneau du bureau contre l\'intégration dans une autre page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrateur');

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
