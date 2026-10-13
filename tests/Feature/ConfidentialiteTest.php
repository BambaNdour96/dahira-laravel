<?php

use App\Enums\ModeCollecte;
use App\Enums\ProgrammeCollecte;
use App\Enums\StatutCollecte;
use App\Filament\Resources\Collectes\Pages\ViewCollecte;
use App\Filament\Resources\JournalExports\Pages\ListJournalExports;
use App\Models\Collecte;
use App\Models\JournalExport;
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

    $this->super = User::factory()->create(['name' => 'Bamba Ndour']);
    $this->super->assignRole(['super_administrateur', 'administrateur']);

    $this->membre = Membre::factory()->valide()->create(['prenom' => 'Awa', 'telephone' => '+221770009999']);
    $this->collecte = Collecte::query()->create([
        'titre' => 'Magal 2026', 'programme' => ProgrammeCollecte::Magal, 'mode' => ModeCollecte::EngagementLibre,
        'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte,
    ]);
    app(CollecteService::class)->fixerParMembre($this->collecte, $this->membre, 10000);
});

it('fait accepter la charte de confidentialité avant l\'accès au panneau', function () {
    $tresorier = User::factory()->create(['charte_acceptee_le' => null]);
    $tresorier->assignRole('tresorier');

    $this->actingAs($tresorier)->get('/admin')->assertRedirect(route('charte'));
    $this->actingAs($tresorier)->get(route('charte'))->assertOk()->assertSee('Charte de confidentialité du bureau');

    $this->actingAs($tresorier)->post(route('charte.accepter'))->assertSessionHasErrors('accepte');
    $this->actingAs($tresorier)->post(route('charte.accepter'), ['accepte' => '1'])->assertRedirect('/admin');

    expect($tresorier->fresh()->charte_acceptee_le)->not->toBeNull();
    $this->actingAs($tresorier->fresh())->get('/admin')->assertOk();
});

it('n\'exporte les téléphones que sur demande motivée, avec la mention confidentielle et une trace', function () {
    $this->actingAs($this->super);

    $sans = $this->get(route('programmes.export', $this->collecte))->streamedContent();
    expect($sans)->not->toContain('Téléphone')
        ->not->toContain('770009999')
        ->toContain('Document confidentiel')
        ->toContain('exporté par Bamba Ndour');

    $this->get(route('programmes.export', ['collecte' => $this->collecte, 'telephones' => 1]))->assertStatus(422);

    $avec = $this->get(route('programmes.export', ['collecte' => $this->collecte, 'telephones' => 1, 'motif' => 'Relances téléphoniques']))->streamedContent();
    expect($avec)->toContain('Téléphone')->toContain('770009999');

    expect(JournalExport::query()->where('user_id', $this->super->id)->pluck('details')->all())
        ->toContain('Sans téléphones')
        ->toContain('Avec téléphones. Motif : Relances téléphoniques');
});

it('avertit avant le téléchargement, puis lance le fichier demandé', function () {
    $this->actingAs($this->super);

    Livewire::test(ViewCollecte::class, ['record' => $this->collecte->getRouteKey()])
        ->callAction('exporter', data: ['telephones' => true, 'motif' => 'Appels de relance'])
        ->assertHasNoActionErrors()
        ->assertRedirectContains('telephones=1');

    Livewire::test(ViewCollecte::class, ['record' => $this->collecte->getRouteKey()])
        ->callAction('exporter', data: ['telephones' => true])
        ->assertHasActionErrors(['motif' => 'required']);

    Livewire::test(ViewCollecte::class, ['record' => $this->collecte->getRouteKey()])
        ->callAction('rapport')
        ->assertRedirectContains('/programmes/');
});

it('trace chaque rapport téléchargé et montre le journal au seul super administrateur', function () {
    $this->actingAs($this->super)->get(route('programmes.rapport', $this->collecte))->assertOk();

    expect(JournalExport::query()->value('document'))->toBe('Rapport du programme (PDF) : Magal 2026');

    Livewire::test(ListJournalExports::class)->assertOk()->assertSee('Rapport du programme (PDF) : Magal 2026');

    $tresorier = User::factory()->create();
    $tresorier->assignRole('tresorier');
    $this->actingAs($tresorier);
    Livewire::test(ListJournalExports::class)->assertForbidden();
});
