<?php

use App\Enums\ModeCollecte;
use App\Enums\ProgrammeCollecte;
use App\Enums\StatutCollecte;
use App\Filament\Resources\Collectes\Pages\ViewCollecte;
use App\Filament\Resources\Collectes\Widgets\CollecteAnalyse;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\User;
use App\Services\CollecteService;
use Database\Seeders\RolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole(['super_administrateur', 'administrateur']);

    $this->dakar = Cellule::factory()->create(['nom' => 'Dakar']);
    $this->kaolack = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->awa = Membre::factory()->for($this->dakar)->valide()->create(['prenom' => 'Awa', 'nom' => 'Diop']);
    $this->moussa = Membre::factory()->for($this->kaolack)->valide()->create(['prenom' => 'Moussa', 'nom' => 'Fall']);
    Membre::factory()->for($this->kaolack)->valide()->create();

    $evenement = Evenement::query()->create(['titre' => 'Magal 2026', 'lieu' => 'Touba', 'debut' => now()->addWeek()]);
    $this->collecte = Collecte::query()->create([
        'titre' => 'Magal 2026', 'programme' => ProgrammeCollecte::Magal, 'mode' => ModeCollecte::EngagementLibre,
        'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte, 'evenement_id' => $evenement->id,
    ]);

    $service = app(CollecteService::class);
    $service->fixerParMembre($this->collecte, $this->awa, 10000);
    $service->fixerParMembre($this->collecte, $this->moussa, 20000);
    $service->enregistrerEspeces($this->collecte, $this->awa, 10000, $this->super);
});

it('calcule l\'avancement par cellule', function () {
    $cellules = app(CollecteService::class)->parCellule($this->collecte);

    expect(array_keys($cellules))->toBe(['Dakar', 'Kaolack'])
        ->and($cellules['Dakar'])->toBe(['concernes' => 1, 'engages' => 1, 'a_jour' => 1, 'engage' => 10000, 'verse' => 10000, 'taux' => 100])
        ->and($cellules['Kaolack']['concernes'])->toBe(2)
        ->and($cellules['Kaolack']['taux'])->toBe(0);
});

it('affiche l\'analyse : participation, engagement moyen, versements, Wave ou espèces', function () {
    $this->actingAs($this->super);

    Livewire::test(CollecteAnalyse::class, ['record' => $this->collecte])
        ->assertSee('67 %')
        ->assertSee('15 000 FCFA')
        ->assertSee('0 % / 100 %');

    Livewire::test(ViewCollecte::class, ['record' => $this->collecte->getRouteKey()])
        ->assertActionVisible('exporter')
        ->assertActionVisible('rapport');
});

it('exporte les engagements pour Excel, et produit le rapport PDF du programme', function () {
    $this->actingAs($this->super);

    $export = $this->get(route('programmes.export', $this->collecte));
    $export->assertOk();
    $contenu = $export->streamedContent();

    expect($contenu)->toStartWith("\xEF\xBB\xBF")
        ->toContain('Matricule;Prénom;Nom;Cellule')
        ->and(mb_strtolower($contenu))->toContain('awa;diop;dakar')
        ->toContain('à jour');

    $this->get(route('programmes.rapport', $this->collecte))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('réserve l\'export et le rapport au bureau et à Suivi et Évaluation', function () {
    $jewrine = User::factory()->create();
    $jewrine->assignRole('responsable_section');

    $this->actingAs($jewrine)->get(route('programmes.export', $this->collecte))->assertForbidden();
    $this->actingAs($jewrine)->get(route('programmes.rapport', $this->collecte))->assertForbidden();

    $suivi = User::factory()->create();
    $suivi->assignRole('suivi_evaluation');
    $this->actingAs($suivi)->get(route('programmes.rapport', $this->collecte))->assertOk();
});
