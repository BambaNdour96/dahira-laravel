<?php

use App\Enums\ModeCollecte;
use App\Enums\MoyenPaiement;
use App\Enums\ProgrammeCollecte;
use App\Enums\Sexe;
use App\Enums\StatutCollecte;
use App\Filament\Resources\Sympathisants\Pages\ManageSympathisants;
use App\Filament\Widgets\SympathisantsChiffres;
use App\Models\Cellule;
use App\Models\CellulePointeur;
use App\Models\Collecte;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Sympathisant;
use App\Models\User;
use App\Models\VisiteSympathisant;
use App\Services\PointageService;
use App\Services\SympathisantService;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->cellule = Cellule::factory()->create(['nom' => 'Kaolack']);
    $compte = User::factory()->create();
    $compte->assignRole('membre');
    $jewrine = Membre::factory()->for($compte)->for($this->cellule)->valide()->create(['sexe' => Sexe::Homme]);
    $this->jewrine = nommerPourLeTest($compte, 'jewrine_cellule', $this->cellule);

    $this->seance = Evenement::query()->create([
        'type' => Evenement::SEANCE, 'titre' => 'Dahira de la cellule Kaolack', 'lieu' => 'Médina',
        'debut' => now(), 'cellule_id' => $this->cellule->id,
    ]);
    $this->service = app(SympathisantService::class);
});

it('enregistre un nouveau sympathisant au pointage, avec son accord', function () {
    $this->actingAs($this->jewrine)->post(route('pointage.sympathisant.nouveau', $this->seance), [
        'prenom' => 'Ousmane', 'nom' => 'Sarr', 'sexe' => 'H', 'telephone' => '77 123 45 67',
    ])->assertSessionHasErrors('accord');

    $this->actingAs($this->jewrine)->post(route('pointage.sympathisant.nouveau', $this->seance), [
        'prenom' => 'Ousmane', 'nom' => 'Sarr', 'sexe' => 'H', 'telephone' => '77 123 45 67', 'accord' => '1',
    ])->assertSessionHas('pointage_succes');

    $sympathisant = Sympathisant::query()->firstOrFail();
    expect($sympathisant->nom)->toBe('SARR')
        ->and($sympathisant->telephone)->toBe('+221771234567')
        ->and(VisiteSympathisant::query()->count())->toBe(1);

    $this->actingAs($this->jewrine)->post(route('pointage.visiteur', $this->seance), ['ecart' => '1']);
    $this->actingAs($this->jewrine)->post(route('pointage.visiteur', $this->seance), ['ecart' => '1']);

    expect(app(PointageService::class)->bilan($this->seance->fresh()))->toMatchArray(['sympathisants' => 1, 'visiteurs' => 2, 'total' => 3]);

    $this->actingAs($this->jewrine)->get(route('pointage.show', $this->seance))
        ->assertSee('Fréquentation totale')->assertSee('Ousmane')->assertSee('Nouveau sympathisant');
});

it('respecte le périmètre : une pointeuse n\'enregistre que des sœurs', function () {
    $compte = User::factory()->create();
    $compte->assignRole('membre');
    Membre::factory()->for($compte)->for($this->cellule)->valide()->create(['sexe' => Sexe::Femme]);
    CellulePointeur::query()->create(['cellule_id' => $this->cellule->id, 'user_id' => $compte->id, 'perimetre' => 'femmes']);

    expect(fn () => $this->service->accueillir($this->seance, ['prenom' => 'Ali', 'nom' => 'Ba', 'sexe' => 'H', 'accord' => true], $compte))
        ->toThrow(DomainException::class)
        ->and($this->service->accueillir($this->seance, ['prenom' => 'Khady', 'nom' => 'Ba', 'sexe' => 'F', 'accord' => true], $compte)->sexe)
        ->toBe(Sexe::Femme);
});

it('repère les fidèles à inviter et relie le sympathisant qui devient membre', function () {
    $sympathisant = $this->service->accueillir($this->seance, ['prenom' => 'Ousmane', 'nom' => 'Sarr', 'sexe' => 'H', 'telephone' => '771234567', 'accord' => true], $this->jewrine);

    foreach ([7, 14] as $jours) {
        $passee = Evenement::query()->create(['type' => Evenement::SEANCE, 'titre' => 'Dahira', 'lieu' => 'Médina', 'debut' => now()->subDays($jours), 'cellule_id' => $this->cellule->id]);
        VisiteSympathisant::query()->create(['evenement_id' => $passee->id, 'sympathisant_id' => $sympathisant->id]);
    }

    expect($sympathisant->aInviter())->toBeTrue()
        ->and($this->service->lienInvitation($sympathisant))->toStartWith('https://wa.me/221771234567?text=');

    $this->actingAs($this->jewrine);
    Livewire::test(ManageSympathisants::class)
        ->assertSee('À inviter')
        ->assertActionVisible(TestAction::make('inviter')->table($sympathisant))
        ->assertActionHidden(TestAction::make('participation')->table($sympathisant));

    Membre::factory()->for($this->cellule)->create(['telephone' => '+221771234567']);
    expect($sympathisant->fresh()->membre_id)->not->toBeNull()
        ->and($sympathisant->fresh()->aInviter())->toBeFalse();
});

it('enregistre la participation d\'un sympathisant, avec reçu, comptée à part dans la collecte', function () {
    $sympathisant = $this->service->accueillir($this->seance, ['prenom' => 'Ousmane', 'nom' => 'Sarr', 'sexe' => 'H', 'accord' => true], $this->jewrine);
    $collecte = Collecte::query()->create([
        'titre' => 'Magal 2026', 'programme' => ProgrammeCollecte::Magal, 'mode' => ModeCollecte::EngagementLibre,
        'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte,
    ]);
    $tresorier = User::factory()->create();
    $tresorier->assignRole('jewrine_finance');

    expect(fn () => $this->service->participer($collecte, $sympathisant, 5000, MoyenPaiement::Especes, $this->jewrine))->toThrow(DomainException::class);

    $this->actingAs($tresorier);
    Livewire::test(ManageSympathisants::class)
        ->callAction(TestAction::make('participation')->table($sympathisant), ['collecte_id' => $collecte->id, 'montant' => 15000, 'moyen' => 'especes'])
        ->assertHasNoActionErrors()
        ->assertRedirectContains('/recus-sympathisants/');

    expect($collecte->totalSympathisants())->toBe(15000)
        ->and($collecte->totalVerse())->toBe(15000)
        ->and($sympathisant->participations()->value('numero_recu'))->toBe('SYM-'.now()->format('Y').'-0001');

    $this->get(route('sympathisants.recu', $sympathisant->participations()->firstOrFail()))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($this->jewrine)->get(route('sympathisants.recu', $sympathisant->participations()->firstOrFail()))->assertForbidden();
});

it('affiche les chiffres au bureau et anonymise les absents depuis 2 ans', function () {
    $ancien = $this->service->accueillir($this->seance, ['prenom' => 'Ancien', 'nom' => 'Visiteur', 'sexe' => 'H', 'accord' => true], $this->jewrine);
    $ancien->forceFill(['derniere_visite_le' => now()->subYears(3)])->save();
    $this->service->accueillir($this->seance, ['prenom' => 'Récent', 'nom' => 'Visiteur', 'sexe' => 'H', 'accord' => true], $this->jewrine);

    $super = User::factory()->create();
    $super->assignRole('super_administrateur');
    $this->actingAs($super);
    Livewire::test(SympathisantsChiffres::class)->assertSee('Devenus membres');

    expect($this->service->purger())->toBe(1)
        ->and(Sympathisant::query()->auRegistre()->pluck('prenom')->all())->toBe(['Récent'])
        ->and($ancien->refresh()->nomComplet())->toBe('Sympathisant anonyme')
        ->and($ancien->anonymise_le)->not->toBeNull()
        ->and($ancien->visites()->count())->toBe(1)
        ->and($this->service->purger())->toBe(0);
});
