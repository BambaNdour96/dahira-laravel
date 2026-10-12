<?php

use App\Enums\SanteProjet;
use App\Enums\StatutProjet;
use App\Enums\TypeSuivi;
use App\Filament\Resources\Projets\Pages\CreateProjet;
use App\Filament\Resources\Projets\Pages\ListProjets;
use App\Filament\Resources\Projets\Pages\ViewProjet;
use App\Filament\Resources\Projets\Widgets\EtapesDuProjet;
use App\Filament\Resources\Projets\Widgets\IndicateursDuProjet;
use App\Filament\Resources\Projets\Widgets\JournalDuProjet;
use App\Filament\Resources\Projets\Widgets\ProjetChiffres;
use App\Filament\Widgets\ProjetsEnCours;
use App\Models\Membre;
use App\Models\Projet;
use App\Models\User;
use App\Services\CompteBureauService;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function projetDeTest(array $attributs = []): Projet
{
    return Projet::query()->create(array_merge([
        'titre' => 'Construction de la salle de lecture',
        'objectif' => 'Offrir une salle de lecture du Coran aux membres.',
        'date_debut' => now()->subDays(50),
        'date_fin' => now()->addDays(50),
        'budget' => 2000000,
    ], $attributs));
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $compte = User::factory()->create();
    $compte->assignRole('membre');
    $membre = Membre::factory()->for($compte)->valide()->create();
    $this->suivi = app(CompteBureauService::class)->nommer($membre, 'suivi_evaluation')->fresh();

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('tresorier');
});

it('nomme un membre de Suivi et Évaluation, qui accède aux seuls projets', function () {
    expect($this->suivi->hasRole('suivi_evaluation'))->toBeTrue()
        ->and($this->suivi->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and($this->suivi->can('create', Projet::class))->toBeTrue()
        ->and($this->suivi->can('viewAny', Membre::class))->toBeFalse()
        ->and($this->tresorier->can('viewAny', Projet::class))->toBeTrue()
        ->and($this->tresorier->can('create', Projet::class))->toBeFalse()
        ->and($this->suivi->can('delete', projetDeTest()))->toBeFalse();
});

it('calcule l\'avancement, le temps écoulé et le feu tricolore', function () {
    $projet = projetDeTest();

    $projet->jalons()->create(['titre' => 'Plans', 'echeance' => now()->subDays(40), 'realise_le' => now()->subDays(41)]);
    $projet->jalons()->create(['titre' => 'Fondations', 'echeance' => now()->addDays(10)]);

    expect($projet->avancement())->toBe(50)
        ->and($projet->tempsEcoule())->toBeBetween(49, 52)
        ->and($projet->sante())->toBe(SanteProjet::Vert);

    $projet->jalons()->create(['titre' => 'Achat du terrain', 'echeance' => now()->subDays(5)]);
    expect($projet->fresh()->sante())->toBe(SanteProjet::Orange);

    $projet->jalons()->where('titre', 'Achat du terrain')->update(['echeance' => now()->subDays(20)]);
    expect($projet->fresh()->sante())->toBe(SanteProjet::Rouge);

    $projet->update(['statut' => StatutProjet::Termine]);
    expect($projet->fresh()->sante())->toBe(SanteProjet::Clos);
});

it('passe au rouge quand les dépenses dépassent le budget', function () {
    $projet = projetDeTest(['budget' => 100000]);
    $projet->suivis()->create(['type' => TypeSuivi::Depense, 'texte' => 'Ciment', 'montant' => 150000]);

    expect($projet->depenses())->toBe(150000)
        ->and($projet->diagnostic())->toBe([SanteProjet::Rouge, 'Les dépenses dépassent le budget.']);
});

it('crée un projet, puis gère ses étapes, indicateurs et journal depuis son tableau de bord', function () {
    $this->actingAs($this->suivi);

    Livewire::test(CreateProjet::class)
        ->fillForm([
            'titre' => 'Formation des jeunes',
            'objectif' => 'Former 50 jeunes aux Xassaïdes.',
            'date_debut' => now()->format('Y-m-d'),
            'date_fin' => now()->addMonths(6)->format('Y-m-d'),
            'statut' => StatutProjet::EnCours->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $projet = Projet::query()->where('titre', 'Formation des jeunes')->firstOrFail();
    expect($projet->cree_par)->toBe($this->suivi->id);

    Livewire::test(EtapesDuProjet::class, ['record' => $projet])
        ->callAction(TestAction::make('ajouterEtape')->table(), data: ['titre' => 'Recrutement', 'echeance' => now()->addMonth()->format('Y-m-d')])
        ->assertHasNoActionErrors();

    $etape = $projet->jalons()->firstOrFail();
    Livewire::test(EtapesDuProjet::class, ['record' => $projet])
        ->callAction(TestAction::make('realiser')->table($etape), data: ['realise_le' => now()->format('Y-m-d')])
        ->assertHasNoActionErrors();

    Livewire::test(IndicateursDuProjet::class, ['record' => $projet])
        ->callAction(TestAction::make('ajouterIndicateur')->table(), data: ['libelle' => 'Jeunes formés', 'cible' => 50, 'unite' => 'jeunes'])
        ->assertHasNoActionErrors();

    $indicateur = $projet->indicateurs()->firstOrFail();
    Livewire::test(IndicateursDuProjet::class, ['record' => $projet])
        ->callAction(TestAction::make('mesurer')->table($indicateur), data: ['valeur' => 20])
        ->assertHasNoActionErrors();

    Livewire::test(JournalDuProjet::class, ['record' => $projet])
        ->callAction(TestAction::make('noter')->table(), data: ['type' => TypeSuivi::Depense->value, 'texte' => 'Achat de livres', 'montant' => 30000])
        ->assertHasNoActionErrors();

    expect($projet->fresh()->avancement())->toBe(100)
        ->and($indicateur->fresh()->atteinte())->toBe(40)
        ->and($projet->depenses())->toBe(30000)
        ->and($projet->suivis()->value('auteur_id'))->toBe($this->suivi->id);
});

it('affiche la liste, le tableau de bord, les chiffres et le rapport PDF', function () {
    $projet = projetDeTest(['statut' => StatutProjet::EnCours]);
    $this->actingAs($this->suivi);

    Livewire::test(ListProjets::class)->assertOk()->assertSee('Construction de la salle de lecture');
    Livewire::test(ViewProjet::class, ['record' => $projet->getRouteKey()])->assertOk();
    Livewire::test(ProjetChiffres::class, ['record' => $projet])->assertSee('Santé du projet')->assertSee('Avancement');
    Livewire::test(ProjetsEnCours::class)->assertSee('Construction de la salle de lecture');

    $this->get(route('projets.rapport', $projet))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $simple = User::factory()->create();
    $simple->assignRole('membre');
    $this->actingAs($simple)->get(route('projets.rapport', $projet))->assertForbidden();
});
