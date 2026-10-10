<?php

use App\Enums\StatutMembre;
use App\Filament\Resources\Membres\Pages\ListMembres;
use App\Filament\Widgets\RapportFinancier;
use App\Models\Cellule;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Services\CompteBureauService;
use App\Services\CotisationService;
use App\Services\PaiementService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/*
 * Droits des quatre postes du bureau : Jewrine de cellule, Jewrine finance,
 * Secrétaire général et Jewrine national.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $poste = function (string $role): User {
        $compte = User::factory()->create();
        $compte->assignRole('membre');
        $membre = Membre::factory()->for($compte)->valide()->create();

        return app(CompteBureauService::class)->nommer($membre, $role)->fresh();
    };

    $this->national = $poste('jewrine_national');
    $this->secretaire = $poste('secretaire_general');
    $this->tresorier = $poste('tresorier');
});

it('propose les vrais postes du bureau, sans « Administrateur »', function () {
    expect(CompteBureauService::ROLES)->toBe([
        'jewrine_national' => 'Jewrine national',
        'secretaire_general' => 'Secrétaire général',
        'tresorier' => 'Jewrine finance',
        'responsable_section' => 'Jewrine de cellule',
    ]);
});

it('donne à la direction le socle commun et son poste propre', function () {
    expect($this->national->hasAllRoles(['jewrine_national', 'administrateur', 'membre']))->toBeTrue()
        ->and($this->secretaire->hasAllRoles(['secretaire_general', 'administrateur', 'membre']))->toBeTrue()
        ->and($this->tresorier->hasRole('administrateur'))->toBeFalse()
        ->and($this->national->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('réserve la confirmation des paiements des membres au Jewrine finance', function () {
    $membre = Membre::factory()->valide()->create(['valide_le' => now()->subMonths(2)]);
    $paiement = app(CotisationService::class)->declarer($membre, 'T_DROITS0000000001', 1);

    expect($this->secretaire->can('update', $paiement))->toBeFalse()
        ->and($this->national->can('update', $paiement))->toBeFalse()
        ->and($this->tresorier->can('update', $paiement))->toBeTrue()
        ->and($this->national->can('create', Paiement::class))->toBeFalse()
        ->and(fn () => app(PaiementService::class)->confirmer($paiement, $this->national))
        ->toThrow(DomainException::class, 'Seul le Jewrine finance');

    app(PaiementService::class)->confirmer($paiement, $this->tresorier);

    expect($paiement->fresh()->numero_recu)->not->toBeNull();
});

it('laisse suspendre et réactiver au seul Jewrine national', function () {
    $membre = Membre::factory()->valide()->create();

    expect($this->national->can('changerStatut', [$membre, StatutMembre::Suspendu]))->toBeTrue()
        ->and($this->secretaire->can('changerStatut', [$membre, StatutMembre::Suspendu]))->toBeFalse();

    $membre->forceFill(['statut' => StatutMembre::Suspendu])->save();

    expect($this->national->can('changerStatut', [$membre->fresh(), StatutMembre::Valide]))->toBeTrue()
        ->and($this->secretaire->can('changerStatut', [$membre->fresh(), StatutMembre::Valide]))->toBeFalse();
});

it('laisse la direction accepter les demandes de toutes les cellules, en secours', function () {
    $demande = Membre::factory()->create(['statut' => StatutMembre::EnAttente]);

    expect($this->secretaire->can('changerStatut', [$demande, StatutMembre::Accepte]))->toBeTrue()
        ->and($this->national->can('changerStatut', [$demande, StatutMembre::Accepte]))->toBeTrue()
        ->and($this->tresorier->can('changerStatut', [$demande, StatutMembre::Accepte]))->toBeFalse();
});

it('confie la dispense de cotisation au Jewrine national', function () {
    $membre = Membre::factory()->valide()->create(['valide_le' => now()->subMonths(2)]);

    $this->actingAs($this->secretaire);
    Livewire::test(ListMembres::class)->assertActionHidden(TestAction::make('exonerer')->table($membre));

    $this->actingAs($this->national);
    Livewire::test(ListMembres::class)
        ->callAction(TestAction::make('exonerer')->table($membre), data: ['nombre_mois' => 1, 'motif' => 'Étudiant sans revenu'])
        ->assertHasNoActionErrors();
});

it('confie les données de référence au Secrétaire général', function () {
    expect($this->secretaire->can('create', Cellule::class))->toBeTrue()
        ->and($this->national->can('create', Cellule::class))->toBeFalse()
        ->and($this->tresorier->can('create', Cellule::class))->toBeFalse();
});

it('confie les événements au Secrétaire général et au Jewrine national', function () {
    expect($this->secretaire->can('create', Evenement::class))->toBeTrue()
        ->and($this->national->can('create', Evenement::class))->toBeTrue()
        ->and($this->tresorier->can('create', Evenement::class))->toBeFalse();

    $this->actingAs($this->secretaire)->get('/admin/evenements')->assertOk();
});

it('confie les relances au Jewrine finance, pas à la direction', function () {
    $this->actingAs($this->tresorier)->get('/admin/relances')->assertOk();
    $this->actingAs($this->secretaire)->get('/admin/relances')->assertForbidden();
});

it('montre le rapport financier en lecture à la direction', function () {
    $this->actingAs($this->national);

    expect(RapportFinancier::canView())->toBeTrue();
});
