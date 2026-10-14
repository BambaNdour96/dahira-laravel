<?php

use App\Models\Membre;
use App\Models\User;
use App\Services\CotisationService;
use App\Services\PaiementService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');

    $this->user = User::factory()->create();
    $this->user->assignRole('membre');
    $this->membre = Membre::factory()->for($this->user)->accepte()->create();

    // Un reçu d'inscription, puis un reçu de cotisation.
    $this->inscription = app(PaiementService::class)->enregistrerEspeces($this->membre, $this->tresorier);
    $this->cotisation = app(CotisationService::class)->enregistrerEspeces($this->membre->refresh(), 2, $this->tresorier);
});

it('numérote les reçus selon leur nature', function () {
    $annee = now()->format('Y');

    expect($this->inscription->numero_recu)->toBe("INS-{$annee}-0001")
        ->and($this->cotisation->numero_recu)->toBe("COT-{$annee}-0001");
});

it('liste tous les reçus du membre', function () {
    $this->actingAs($this->user)->get('/mes-recus')
        ->assertOk()
        ->assertSee($this->inscription->numero_recu)
        ->assertSee($this->cotisation->numero_recu)
        ->assertSee('2 000 FCFA');
});

it('filtre les reçus par nature', function () {
    DB::table('notifications')->delete();

    $this->actingAs($this->user)->get('/mes-recus?type=cotisation')
        ->assertOk()
        ->assertSee($this->cotisation->numero_recu)
        ->assertDontSee($this->inscription->numero_recu);
});

it('nomme le fichier PDF selon la nature du reçu', function () {
    $this->actingAs($this->user)->get(route('recus.show', $this->inscription))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="Recu-inscription-'.$this->inscription->numero_recu.'.pdf"');
});

it('ne montre pas les reçus d\'un autre membre', function () {
    $autre = User::factory()->create();
    $autre->assignRole('membre');
    Membre::factory()->for($autre)->valide()->create();

    $this->actingAs($autre)->get('/mes-recus')
        ->assertOk()
        ->assertDontSee($this->inscription->numero_recu);
});
