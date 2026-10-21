<?php

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Filament\Resources\Collectes\Pages\CreateCollecte;
use App\Filament\Resources\Collectes\Pages\ListCollectes;
use App\Filament\Resources\Collectes\Widgets\CollecteChiffres;
use App\Filament\Resources\Collectes\Widgets\CollecteEvolution;
use App\Filament\Resources\Collectes\Widgets\CollecteParCellule;
use App\Filament\Resources\Collectes\Widgets\EngagementsDesMembres;
use App\Models\Collecte;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Services\CollecteService;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');

    $this->compte = User::factory()->create();
    $this->compte->assignRole('membre');
    $this->membre = Membre::factory()->for($this->compte)->valide()->create();

    $this->collecte = Collecte::query()->create([
        'titre' => 'Ziar annuelle 2026',
        'type_programme_id' => typeProgramme('ziar'),
        'mode' => ModeCollecte::Sass,
        'montant' => 20000,
        'objectif' => 300000,
        'ouverture' => now()->subDays(5),
        'date_limite' => now()->addMonth(),
        'statut' => StatutCollecte::Ouverte,
    ]);
});

it('crée un événement depuis le panneau, avec son auteur, et engage d\'office au Sass', function () {
    $this->actingAs($this->super);

    Livewire::test(CreateCollecte::class)
        ->fillForm([
            'type_programme_id' => typeProgramme('prokhane'),
            'titre' => 'Prokhane 2026',
            'debut' => now()->addMonth()->format('Y-m-d H:i'),
            'lieu' => 'Kaolack',
            'mode' => ModeCollecte::Sass->value,
            'montant' => 5000,
            'organisation' => Collecte::COMMISSIONS,
            'ouverture' => now()->format('Y-m-d'),
            'statut' => StatutCollecte::Ouverte->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $prokhane = Collecte::query()->where('titre', 'Prokhane 2026')->firstOrFail();
    expect($prokhane->cree_par)->toBe($this->super->id)
        ->and($prokhane->engagements()->where('membre_id', $this->membre->id)->value('montant'))->toBe(5000);
});

it('clôture puis rouvre une collecte', function () {
    $this->actingAs($this->super);

    Livewire::test(ListCollectes::class)->callAction(TestAction::make('cloturer')->table($this->collecte));
    expect($this->collecte->fresh()->statut)->toBe(StatutCollecte::Cloturee);

    Livewire::test(ListCollectes::class)->callAction(TestAction::make('rouvrir')->table($this->collecte));
    expect($this->collecte->fresh()->statut)->toBe(StatutCollecte::Ouverte);
});

it('modifie, encaisse et retrace un engagement depuis le panneau', function () {
    $this->actingAs($this->super);
    $page = ['record' => $this->collecte];

    $engagement = app(CollecteService::class)->engagementDe($this->collecte, $this->membre);
    app(CollecteService::class)->modifier($engagement, 50000, 'Décision du bureau', $this->super);
    expect($engagement->fresh()->montant)->toBe(50000);

    Livewire::test(EngagementsDesMembres::class, $page)
        ->callAction(TestAction::make('modifier')->table($engagement), data: ['montant' => 40000, 'motif' => 'Demande du membre'])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('especes')->table($engagement), data: ['montant' => 15000])
        ->assertHasNoActionErrors()
        ->assertActionVisible(TestAction::make('historique')->table($engagement));

    expect($engagement->historique()->pluck('motif')->all())->toContain('Décision du bureau', 'Demande du membre');

    expect($engagement->fresh()->montant)->toBe(40000)
        ->and($engagement->fresh()->verse())->toBe(15000);

    Livewire::test(EngagementsDesMembres::class, $page)
        ->assertActionVisible(TestAction::make('messageGroupe')->table());
});

it('affiche les graphiques et les chiffres d\'une collecte', function () {
    app(CollecteService::class)->engagementDe($this->collecte, $this->membre);
    app(CollecteService::class)->enregistrerEspeces($this->collecte, $this->membre, 5000, $this->super);
    $this->actingAs($this->super);

    Livewire::test(CollecteChiffres::class, ['record' => $this->collecte])->assertSee('Collecté')->assertSee('Sans engagement');
    Livewire::test(CollecteEvolution::class, ['record' => $this->collecte])->assertOk();
    Livewire::test(CollecteParCellule::class, ['record' => $this->collecte])->assertOk();
});

it('déclare un versement depuis « Mes engagements », et refuse une erreur de saisie', function () {
    app(CollecteService::class)->engagementDe($this->collecte, $this->membre);

    $this->actingAs($this->compte)->post(route('engagements.versement', $this->collecte), [
        'montant' => 10000,
        'reference' => 'T_VERSEMENT0000001',
    ])->assertRedirect(route('engagements.index'));

    expect(Paiement::query()->where('collecte_id', $this->collecte->id)->where('objet', 'collecte')->count())->toBe(1);

    $this->actingAs($this->compte)->post(route('engagements.versement', $this->collecte), [
        'montant' => 15000,
        'reference' => 'T_VERSEMENT0000002',
    ])->assertSessionHasErrors('montant');

    $this->actingAs($this->compte)->post(route('engagements.fixer', $this->collecte), ['montant' => 1000])
        ->assertSessionHasErrors('montant');
});
