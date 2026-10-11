<?php

use App\Enums\ModeCollecte;
use App\Enums\ProgrammeCollecte;
use App\Enums\StatutCollecte;
use App\Filament\Widgets\CotisationsMensuelles;
use App\Filament\Widgets\CotisationsParCellule;
use App\Filament\Widgets\EncaissementsParMois;
use App\Models\Collecte;
use App\Models\Membre;
use App\Models\User;
use App\Services\CollecteService;
use App\Support\MessagesRelance;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->compte = User::factory()->create(['telephone' => '+221770002222']);
    $this->compte->assignRole('membre');
    $this->membre = Membre::factory()->for($this->compte)->valide()->create(['prenom' => 'Awa', 'telephone' => '+221770002222']);

    $this->collecte = Collecte::query()->create([
        'titre' => 'Magal de Touba 2026',
        'programme' => ProgrammeCollecte::Magal,
        'mode' => ModeCollecte::EngagementLibre,
        'ouverture' => now()->subDay(),
        'date_limite' => now()->addMonth(),
        'statut' => StatutCollecte::Ouverte,
    ]);

    $this->engagement = app(CollecteService::class)->fixerParMembre($this->collecte, $this->membre, 20000);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('tresorier');
});

it('relance un engagement sur WhatsApp, avec le reste à verser', function () {
    $reponse = $this->actingAs($this->tresorier)->get(route('relances.collecte', $this->engagement));

    expect($reponse->headers->get('Location'))
        ->toStartWith('https://wa.me/221770002222?text=')
        ->toContain(rawurlencode('Magal de Touba 2026'))
        ->toContain(rawurlencode('20 000 FCFA'));
});

it('réserve la relance des collectes au Jewrine finance et au super administrateur', function () {
    $secretaire = User::factory()->create();
    $secretaire->assignRole(['secretaire_general', 'administrateur']);

    $this->actingAs($secretaire)->get(route('relances.collecte', $this->engagement))->assertForbidden();
});

it('prépare le message de groupe d\'une collecte', function () {
    expect(MessagesRelance::collecteGroupe($this->collecte))
        ->toContain('Magal de Touba 2026')
        ->toContain(route('engagements.index'));
});

it('affiche le suivi des cotisations et les encaissements par nature', function () {
    $this->actingAs($this->tresorier);

    Livewire::test(CotisationsMensuelles::class)->assertSee('Retardataires')->assertSee('Encaissé ce mois');
    Livewire::test(CotisationsParCellule::class)->assertOk();
    Livewire::test(EncaissementsParMois::class)->assertOk();
});
