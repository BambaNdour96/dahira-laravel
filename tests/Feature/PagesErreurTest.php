<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('affiche une page 404 aux couleurs de la Daara', function () {
    $this->get('/une-adresse-qui-nexiste-pas')
        ->assertNotFound()
        ->assertSee('Page introuvable')
        ->assertSee('Daara Nouroud Daareyni Kaolack');
});

it('affiche une page claire après trop de tentatives', function () {
    foreach (range(1, 6) as $essai) {
        $reponse = $this->post('/connexion', ['telephone' => '77 000 99 99', 'password' => 'mauvais1']);
    }

    $reponse->assertStatus(429)->assertSee('Trop de tentatives');
});

it('a une page en français pour chaque erreur courante', function (string $code, string $titre) {
    $this->view('errors.'.$code)->assertSee($titre);
})->with([
    ['419', 'Votre session a expiré'],
    ['500', 'Une erreur est survenue'],
    ['503', 'Maintenance en cours'],
]);
