<?php

use App\Enums\StatutMembre;
use App\Filament\Resources\Membres\Pages\ListMembres;
use App\Filament\Widgets\RapportFinancier;
use App\Models\Cellule;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Poste;
use App\Models\User;
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

        return nommerPourLeTest($compte, $role);
    };

    $this->national = $poste('jewrine_national');
    $this->secretaire = $poste('secretaire_general');
    $this->tresorier = $poste('jewrine_finance');
});

it('propose les vrais postes de la Daara, chacun avec sa casquette', function () {
    expect(Poste::query()->where('actif', true)->orderBy('ordre')->pluck('code', 'nom')->all())->toBe([
        'Jewrine national' => 'jewrine_national',
        'Secrétaire général' => 'secretaire_general',
        'Jewrine finance national' => 'jewrine_finance',
        'Jewrine de cellule' => 'jewrine_cellule',
        'Jewrine de commission' => 'jewrine_commission',
        'Convocateur du Comité directeur' => 'convocateur_comite_directeur',
        'Jewrine du comité de suivi des dépenses' => 'jewrine_suivi_depenses',
    ]);
});

it('donne à chaque poste sa casquette, sans socle « administrateur »', function () {
    expect($this->national->hasAllRoles(['jewrine_national', 'membre']))->toBeTrue()
        ->and($this->national->getRoleNames()->all())->not->toContain('administrateur')
        ->and($this->secretaire->hasAllRoles(['secretaire_general', 'membre']))->toBeTrue()
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

it('fait décider la dispense par le Jewrine national, sans qu\'il la demande lui-même', function () {
    $membre = Membre::factory()->valide()->create(['valide_le' => now()->subMonths(2)]);

    foreach ([$this->secretaire, $this->national] as $responsable) {
        $this->actingAs($responsable);
        Livewire::test(ListMembres::class)->assertActionHidden(TestAction::make('exonerer')->table($membre));
    }

    expect($this->national->can('cotisations.dispenser'))->toBeTrue()
        ->and($this->secretaire->can('cotisations.dispenser'))->toBeFalse();
});

it('réserve les données de référence au super administrateur, pas au Secrétaire général', function () {
    expect($this->secretaire->can('create', Cellule::class))->toBeFalse()
        ->and($this->secretaire->can('update', $this->national->membre))->toBeTrue()
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
