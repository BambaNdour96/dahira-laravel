<?php

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Filament\Resources\Collectes\Pages\CreateCollecte;
use App\Filament\Resources\Collectes\Pages\EditCollecte;
use App\Filament\Resources\Collectes\Pages\ViewCollecte;
use App\Filament\Resources\Collectes\Widgets\JourJDuProgramme;
use App\Models\Collecte;
use App\Models\Evenement;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\Presence;
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
    $this->super->assignRole('super_administrateur');
});

it('crée un programme avec son jour J, en un seul formulaire', function () {
    $this->actingAs($this->super);

    Livewire::test(CreateCollecte::class)
        ->fillForm([
            'type_programme_id' => typeProgramme('magal'),
            'titre' => 'Magal de Touba 2026',
            'mode' => ModeCollecte::Jayante->value,
            'ouverture' => now()->format('Y-m-d'),
            'statut' => StatutCollecte::Ouverte->value,
            'organisation' => Collecte::COMITE,
            'avec_jour_j' => true,
            'itineraire_transport' => true,
            'debut' => now()->addMonth()->format('Y-m-d 10:00:00'),
            'lieu' => 'Touba',
            'objectif' => 5000000,
            'budget' => 3000000,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $collecte = Collecte::query()->where('titre', 'Magal de Touba 2026')->firstOrFail();

    expect($collecte->evenement)->not->toBeNull()
        ->and($collecte->evenement?->titre)->toBe('Magal de Touba 2026')
        ->and($collecte->evenement?->lieu)->toBe('Touba')
        ->and($collecte->itineraire_transport)->toBeTrue()
        ->and($collecte->comite?->type)->toBe(Instance::COMITE_ORGANISATION)
        ->and($collecte->budget)->toBe(3000000);

    Livewire::test(ViewCollecte::class, ['record' => $collecte->getRouteKey()])->assertOk();
    Livewire::test(JourJDuProgramme::class, ['record' => $collecte])->assertSee('Touba')->assertSee('Taux de présence');
});

it('met à jour le jour J, puis le détache sans perdre les présences', function () {
    $this->actingAs($this->super);

    $evenement = Evenement::query()->create(['titre' => 'Prokhane 2026', 'lieu' => 'Kaolack', 'debut' => now()->addWeek()]);
    $collecte = Collecte::query()->create([
        'titre' => 'Prokhane 2026', 'type_programme_id' => typeProgramme('prokhane'), 'mode' => ModeCollecte::Sass, 'organisation' => Collecte::COMMISSIONS,
        'montant' => 5000, 'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte, 'evenement_id' => $evenement->id,
        'debut' => $evenement->debut, 'lieu' => 'Kaolack',
    ]);

    Livewire::test(EditCollecte::class, ['record' => $collecte->getRouteKey()])
        ->assertSchemaStateSet(['avec_jour_j' => true, 'lieu' => 'Kaolack'])
        ->fillForm(['lieu' => 'Mosquée de Kaolack'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($evenement->fresh()->lieu)->toBe('Mosquée de Kaolack');

    Livewire::test(EditCollecte::class, ['record' => $collecte->getRouteKey()])
        ->fillForm(['avec_jour_j' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($collecte->fresh()->evenement_id)->toBeNull()
        ->and(Evenement::query()->whereKey($evenement->id)->exists())->toBeTrue();

    Livewire::test(JourJDuProgramme::class, ['record' => $collecte->fresh()])->assertSee('Aucun');
});

it('montre le jour J au membre, puis sa présence', function () {
    $compte = User::factory()->create();
    $compte->assignRole('membre');
    $membre = Membre::factory()->for($compte)->valide()->create();

    $evenement = Evenement::query()->create(['titre' => 'Ziar 2026', 'lieu' => 'Médina Baye', 'debut' => now()->addDays(3)]);
    $collecte = Collecte::query()->create([
        'titre' => 'Ziar 2026', 'type_programme_id' => typeProgramme('ziar'), 'mode' => ModeCollecte::Jayante,
        'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte, 'evenement_id' => $evenement->id,
    ]);
    app(CollecteService::class)->fixerParMembre($collecte, $membre, 5000);

    $this->actingAs($compte)->get(route('engagements.index'))
        ->assertSee('Jour J')
        ->assertSee('Médina Baye')
        ->assertSee('Présentez votre carte');

    Presence::query()->create(['evenement_id' => $evenement->id, 'membre_id' => $membre->id]);

    $this->actingAs($compte)->get(route('engagements.index'))->assertSee('Vous étiez présent');
});
