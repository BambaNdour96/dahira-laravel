<?php

use App\Enums\Sexe;
use App\Filament\Resources\Evenements\Pages\ManageEvenements;
use App\Filament\Resources\Seances\Pages\CreateSeance;
use App\Filament\Resources\Seances\Pages\ListSeances;
use App\Filament\Resources\Seances\Pages\ViewSeance;
use App\Filament\Resources\Seances\Widgets\AssiduiteDesMembres;
use App\Filament\Resources\Seances\Widgets\BilanSeance;
use App\Filament\Resources\Seances\Widgets\ListePresence;
use App\Filament\Widgets\FrequentationCellules;
use App\Models\Cellule;
use App\Models\CellulePointeur;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Presence;
use App\Models\User;
use App\Notifications\RappelProgramme;
use App\Services\AssiduiteService;
use App\Services\CarteService;
use App\Services\CompteBureauService;
use App\Services\PointageService;
use Database\Seeders\RolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function membreDeCellule(Cellule $cellule, Sexe $sexe, string $prenom): Membre
{
    $compte = User::factory()->create();
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->for($cellule)->valide()->create(['prenom' => $prenom, 'sexe' => $sexe]);
}

function seanceDe(Cellule $cellule, array $attributs = []): Evenement
{
    return Evenement::query()->create(array_merge([
        'type' => Evenement::SEANCE, 'titre' => 'Dahira de la cellule '.$cellule->nom,
        'lieu' => 'Médina', 'debut' => now(), 'cellule_id' => $cellule->id,
    ], $attributs));
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->kaolack = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->dakar = Cellule::factory()->create(['nom' => 'Dakar']);

    $this->jewrine = membreDeCellule($this->kaolack, Sexe::Homme, 'Ibrahima');
    $this->jewrineCompte = app(CompteBureauService::class)->nommer($this->jewrine, 'responsable_section', $this->kaolack->id)->fresh();
    $this->jewrineF = membreDeCellule($this->kaolack, Sexe::Femme, 'Aminata');
    $this->jewrineFCompte = app(CompteBureauService::class)->nommer($this->jewrineF, 'jewrine_femmes', $this->kaolack->id)->fresh();

    $this->frere = membreDeCellule($this->kaolack, Sexe::Homme, 'Moussa');
    $this->soeur = membreDeCellule($this->kaolack, Sexe::Femme, 'Awa');
    $this->pointage = app(PointageService::class);
});

it('nomme une Jewrine des femmes, rattachée à sa cellule, avec accès au panneau', function () {
    expect($this->kaolack->fresh()->jewrine_femmes_id)->toBe($this->jewrineFCompte->id)
        ->and($this->kaolack->fresh()->responsable_id)->toBe($this->jewrineCompte->id)
        ->and($this->jewrineFCompte->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('laisse le Jewrine créer une séance de sa seule cellule', function () {
    $this->actingAs($this->jewrineCompte);

    Livewire::test(CreateSeance::class)
        ->fillForm(['cellule_id' => $this->kaolack->id, 'lieu' => 'Chez Serigne Fall', 'debut' => now()->addDays(3)->format('Y-m-d 21:00:00'), 'description' => 'Récital de Xassaïdes'])
        ->call('create')
        ->assertHasNoFormErrors();

    $seance = Evenement::query()->where('type', Evenement::SEANCE)->firstOrFail();
    expect($seance->titre)->toBe('Dahira de la cellule Kaolack');

    Livewire::test(CreateSeance::class)
        ->fillForm(['cellule_id' => $this->dakar->id, 'lieu' => 'Dakar', 'debut' => now()->addDay()->format('Y-m-d 21:00:00')])
        ->call('create');
    expect(Evenement::query()->where('cellule_id', $this->dakar->id)->exists())->toBeFalse();

    Livewire::test(ListSeances::class)->assertOk()->assertSee('Chez Serigne Fall');

    $super = User::factory()->create();
    $super->assignRole(['super_administrateur', 'administrateur']);
    $this->actingAs($super);
    Livewire::test(ManageEvenements::class)->assertOk()->assertDontSee('Chez Serigne Fall');
});

it('fait pointer les frères par un frère et les sœurs par une sœur, avec le secours du Jewrine', function () {
    $seance = seanceDe($this->kaolack);
    $pointeurF = membreDeCellule($this->kaolack, Sexe::Femme, 'Fatou');
    CellulePointeur::query()->create(['cellule_id' => $this->kaolack->id, 'user_id' => $pointeurF->user_id, 'perimetre' => 'femmes']);
    $comptePointeuse = User::query()->findOrFail($pointeurF->user_id);

    expect(fn () => $this->pointage->pointer($seance, $this->frere, $this->jewrineFCompte))->toThrow(DomainException::class)
        ->and(fn () => $this->pointage->pointer($seance, $this->frere, $comptePointeuse))->toThrow(DomainException::class);

    expect($this->pointage->pointer($seance, $this->soeur, $comptePointeuse)->mode)->toBe('scan')
        ->and($this->pointage->pointer($seance, $this->frere, $this->jewrineCompte, 'manuel')->mode)->toBe('manuel');

    $this->pointage->retirer($seance, $this->soeur, $comptePointeuse);
    expect($this->pointage->pointer($seance, $this->soeur, $this->jewrineCompte, 'manuel')->mode)->toBe('secours');
});

it('pointe à la main, note les absents excusés et calcule le bilan', function () {
    $seance = seanceDe($this->kaolack);

    $this->actingAs($this->jewrineCompte)->get(route('pointage.show', $seance))
        ->assertOk()->assertSee('Moussa')->assertSee('Awa')->assertSee('Absent excusé');

    $this->actingAs($this->jewrineCompte)->post(route('pointage.pointer', [$seance, $this->frere]), ['action' => 'present'])
        ->assertSessionHas('pointage_succes');
    $this->actingAs($this->jewrineCompte)->post(route('pointage.pointer', [$seance, $this->soeur]), ['action' => 'excuse'])
        ->assertSessionHas('pointage_erreur');
    $this->actingAs($this->jewrineCompte)->post(route('pointage.pointer', [$seance, $this->soeur]), ['action' => 'excuse', 'motif' => 'Voyage']);

    expect($this->pointage->bilan($seance))->toMatchArray(['presents' => 1, 'freres' => 1, 'soeurs' => 0, 'excuses' => 1, 'concernes' => 4]);

    $this->actingAs($this->jewrineFCompte)->get(route('pointage.show', $seance))->assertOk()->assertDontSee('Moussa')->assertSee('Awa');

    $this->actingAs($this->jewrineCompte);
    Livewire::test(ViewSeance::class, ['record' => $seance->getRouteKey()])->assertOk();
    Livewire::test(BilanSeance::class, ['record' => $seance])->assertSee('Excusés');
    Livewire::test(ListePresence::class, ['record' => $seance])->assertSee('Voyage');
});

it('ferme la liste après la séance, sauf pour la direction', function () {
    $seance = seanceDe($this->kaolack, ['debut' => now()->subDays(2)]);
    $national = User::factory()->create();
    $national->assignRole(['jewrine_national', 'administrateur']);

    expect(fn () => $this->pointage->pointer($seance, $this->frere, $this->jewrineCompte, 'manuel'))->toThrow(DomainException::class, 'close')
        ->and($this->pointage->pointer($seance, $this->frere, $national, 'manuel')->statut)->toBe('present');
});

it('pointe au scan de la carte avec les nouvelles règles', function () {
    $seance = seanceDe($this->kaolack);
    $carte = app(CarteService::class)->emettre($this->soeur);

    $this->actingAs($this->jewrineFCompte)->get(route('verification', $carte->jeton))->assertSee('Marquer présent');
    $this->actingAs($this->jewrineFCompte)->post(route('presences.store', [$carte->jeton, $seance]))->assertSessionHas('presence_succes');

    expect(Presence::query()->where('membre_id', $this->soeur->id)->value('statut'))->toBe('present');
});

it('calcule l\'assiduité, alerte après 3 absences de suite et rappelle la séance du jour', function () {
    Notification::fake();
    $this->soeur->forceFill(['valide_le' => now()->subMonths(6)])->save();

    foreach ([21, 14, 7, 1] as $jours) {
        $seance = seanceDe($this->kaolack, ['debut' => now()->subDays($jours)->setTime(21, 0)]);
        if ($jours === 21) {
            Presence::query()->create(['evenement_id' => $seance->id, 'membre_id' => $this->soeur->id]);
        }
    }

    $assiduite = app(AssiduiteService::class)->pour($this->soeur->fresh());
    expect($assiduite['seances'])->toBe(4)->and($assiduite['presents'])->toBe(1)->and($assiduite['absences_de_suite'])->toBe(3);

    seanceDe($this->kaolack, ['debut' => now()->endOfDay()->subSeconds(30)]);
    $bilan = app(AssiduiteService::class)->routineDuMatin();

    expect($bilan['rappels'])->toBe(4)->and($bilan['alertes'])->toBeGreaterThan(0);
    Notification::assertSentTo(User::query()->findOrFail($this->soeur->user_id), RappelProgramme::class);
    $this->artisan('seances:routine')->assertSuccessful();

    $this->actingAs(User::query()->findOrFail($this->soeur->user_id))->get('/mon-espace')
        ->assertSee('Dahira de ma cellule')->assertSee('Mon assiduité');

    $this->actingAs($this->jewrineFCompte);
    Livewire::test(AssiduiteDesMembres::class)->assertSee('Awa')->assertDontSee('Moussa');
    Livewire::test(FrequentationCellules::class)->assertOk();
});
