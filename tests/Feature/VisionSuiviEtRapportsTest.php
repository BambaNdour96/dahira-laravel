<?php

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Enums\TypeRapport;
use App\Filament\Resources\Collectes\Pages\ListCollectes;
use App\Filament\Resources\Collectes\Widgets\EngagementsDesMembres;
use App\Filament\Resources\Evenements\Pages\ManageEvenements;
use App\Filament\Resources\Rapports\Pages\CreateRapport;
use App\Filament\Resources\Rapports\Pages\ListRapports;
use App\Filament\Resources\Rapports\Pages\ViewRapport;
use App\Filament\Widgets\CollectesEnCours;
use App\Filament\Widgets\CotisationsMensuelles;
use App\Filament\Widgets\ProchainsEvenements;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\RapportSuivi;
use App\Models\User;
use App\Services\CollecteService;
use App\Services\RapportService;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->suivi = User::factory()->create();
    $this->suivi->assignRole('suivi_evaluation');

    $this->jewrine = User::factory()->create();
    $this->jewrine->assignRole('jewrine_cellule');

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');

    $this->celluleA = celluleDirigeePar($this->jewrine);
    $this->celluleB = Cellule::factory()->create();
    $this->membreA = Membre::factory()->for($this->celluleA)->valide()->create(['prenom' => 'Awa']);
    $this->membreB = Membre::factory()->for($this->celluleB)->valide()->create(['prenom' => 'Moussa']);

    $this->collecte = Collecte::query()->create([
        'titre' => 'Magal de Touba 2026',
        'type_programme_id' => typeProgramme('magal'),
        'mode' => ModeCollecte::Jayante,
        'ouverture' => now()->subDay(),
        'statut' => StatutCollecte::Ouverte,
    ]);

    $service = app(CollecteService::class);
    $this->engagementA = $service->fixerParMembre($this->collecte, $this->membreA, 10000);
    $this->engagementB = $service->fixerParMembre($this->collecte, $this->membreB, 15000);
});

it('donne à Suivi et Évaluation une vision complète, en lecture seule', function () {
    $this->actingAs($this->suivi);

    Livewire::test(ListCollectes::class)->assertOk()->assertSee('Magal de Touba 2026');

    Livewire::test(EngagementsDesMembres::class, ['record' => $this->collecte])
        ->assertSee('Awa')
        ->assertSee('Moussa')
        ->assertActionHidden(TestAction::make('modifier')->table($this->engagementA))
        ->assertActionHidden(TestAction::make('especes')->table($this->engagementA))
        ->assertActionHidden(TestAction::make('relancer')->table($this->engagementA));

    Livewire::test(CollectesEnCours::class)->assertSee('Magal de Touba 2026');
    Livewire::test(CotisationsMensuelles::class)->assertSee('Retardataires');

    Evenement::query()->create(['titre' => 'Prokhane 2026', 'lieu' => 'Kaolack', 'debut' => now()->addWeek()]);
    Livewire::test(ProchainsEvenements::class)->assertSee('Prokhane 2026');
    Livewire::test(ManageEvenements::class)->assertOk()->assertSee('Prokhane 2026')->assertActionHidden('create');
});

it('limite le Jewrine de cellule à sa cellule, et le laisse relancer ses membres', function () {
    $this->actingAs($this->jewrine);

    Livewire::test(EngagementsDesMembres::class, ['record' => $this->collecte])
        ->assertSee('Awa')
        ->assertDontSee('Moussa')
        ->assertActionVisible(TestAction::make('relancer')->table($this->engagementA))
        ->assertActionHidden(TestAction::make('modifier')->table($this->engagementA));

    $this->get(route('relances.collecte', $this->engagementA))->assertRedirect();
    $this->get(route('relances.collecte', $this->engagementB))->assertForbidden();
});

it('laisse Suivi et Évaluation rédiger un rapport, puis le publier au bureau', function () {
    $national = User::factory()->create();
    $national->assignRole('jewrine_national');

    $this->actingAs($this->suivi);

    Livewire::test(CreateRapport::class)
        ->fillForm([
            'titre' => 'Bilan de mi-parcours du Magal',
            'type' => TypeRapport::Suivi->value,
            'collecte_id' => $this->collecte->id,
            'contenu' => '<p>La collecte avance bien dans la cellule A.</p>',
            'recommandations' => '<p>Relancer la cellule B.</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rapport = RapportSuivi::query()->firstOrFail();
    expect($rapport->auteur_id)->toBe($this->suivi->id)
        ->and($rapport->estPublie())->toBeFalse()
        ->and($this->tresorier->can('view', $rapport))->toBeFalse();

    Livewire::test(ViewRapport::class, ['record' => $rapport->getRouteKey()])
        ->callAction('publier')
        ->assertHasNoActionErrors();

    expect($rapport->fresh()->estPublie())->toBeTrue()
        ->and($this->tresorier->can('view', $rapport->fresh()))->toBeTrue()
        ->and($this->suivi->can('update', $rapport->fresh()))->toBeFalse()
        ->and($national->notifications()->count())->toBe(1);

    $this->get(route('rapports.pdf', $rapport))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($this->tresorier);
    Livewire::test(ListRapports::class)->assertSee('Bilan de mi-parcours du Magal');
});

it('cache les brouillons des autres, et refuse les rapports aux simples membres', function () {
    $rapport = RapportSuivi::query()->create([
        'titre' => 'Brouillon confidentiel',
        'type' => TypeRapport::Ponctuel,
        'contenu' => '<p>En cours.</p>',
        'auteur_id' => $this->suivi->id,
    ]);

    $this->actingAs($this->tresorier);
    Livewire::test(ListRapports::class)->assertDontSee('Brouillon confidentiel');

    $membre = User::factory()->create();
    $membre->assignRole('membre');
    $this->actingAs($membre)->get(route('rapports.pdf', $rapport))->assertForbidden();

    app(RapportService::class)->publier($rapport, $this->suivi);
    expect(RapportSuivi::propre('<p>Bien</p><script>alert(1)</script>'))->toBe('<p>Bien</p>alert(1)');
});
