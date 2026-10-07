<?php

use App\Models\Membre;
use App\Models\User;
use App\Services\CotisationService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);

    $this->user = User::factory()->create(['password' => 'motdepasse1']);
    $this->user->assignRole('membre');
    $this->membre = Membre::factory()->for($this->user)->valide()->create([
        'prenom' => 'Awa',
        'valide_le' => now()->subMonths(2),
    ]);
});

it('explique les droits et les durées de conservation', function () {
    $this->get('/confidentialite')
        ->assertOk()
        ->assertSee('Vos droits')
        ->assertSee('Commission de Protection des Données Personnelles')
        ->assertSee('Historique des connexions : 12 mois');
});

it('permet au membre de télécharger toutes ses données', function () {
    app(CotisationService::class)->declarer($this->membre, 'TDONNEES1', 1);

    $reponse = $this->actingAs($this->user)->get(route('donnees.export'));

    $reponse->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename="mes-donnees-daara-ndk.json"');

    expect($reponse->json('fiche_membre.prenom'))->toBe('Awa')
        ->and($reponse->json('paiements'))->toHaveCount(1)
        ->and($reponse->json('paiements.0.reference'))->toBe('TDONNEES1');
});

it('ne livre jamais de secret dans l\'export', function () {
    $contenu = $this->actingAs($this->user)->get(route('donnees.export'))->getContent();

    expect($contenu)->not->toContain('password')
        ->and($contenu)->not->toContain('app_authentication')
        ->and($contenu)->not->toContain('jeton')
        ->and($contenu)->not->toContain('photo_path');
});

it('réserve l\'export à la personne connectée', function () {
    $this->get(route('donnees.export'))->assertRedirect(route('login'));
});
