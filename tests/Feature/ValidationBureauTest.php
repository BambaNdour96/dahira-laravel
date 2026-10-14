<?php

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Filament\Resources\Paiements\Pages\ManagePaiements;
use App\Models\Membre;
use App\Models\RegleValidation;
use App\Models\User;
use App\Services\CotisationService;
use App\Services\PaiementService;
use Database\Seeders\PostesSeeder;
use Database\Seeders\ReglesValidationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function compteDuBureauAvecPoste(string $poste, array $roles): User
{
    $compte = User::factory()->create();
    $compte->assignRole([...$roles, 'membre']);

    Membre::factory()->for($compte)->valide()->create(['valide_le' => now()->subMonths(2)]);

    return nommerPourLeTest($compte, ['Jewrine finance' => 'jewrine_finance', 'Secrétaire général' => 'secretaire_general', 'Jewrine national' => 'jewrine_national'][$poste]);
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, PostesSeeder::class, ReglesValidationSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);

    $this->jewrineFinance = compteDuBureauAvecPoste('Jewrine finance', ['jewrine_finance']);
    $this->secretaire = compteDuBureauAvecPoste('Secrétaire général', ['secretaire_general']);
    $this->jewrineNational = compteDuBureauAvecPoste('Jewrine national', ['jewrine_national']);

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
});

it('crée la boucle de validation du bureau', function () {
    expect(RegleValidation::query()->count())->toBe(3);
});

it('fait valider le paiement du Jewrine finance par le Secrétaire général', function () {
    $paiement = app(CotisationService::class)->declarer($this->jewrineFinance->membre, 'T_JFINANCE00000001', 1);

    app(PaiementService::class)->confirmer($paiement, $this->secretaire);

    expect($paiement->fresh()->statut)->toBe(StatutPaiement::Confirme);
});

it('refuse qu\'un autre administrateur valide le paiement du Jewrine finance', function () {
    $autreAdmin = User::factory()->create();
    $autreAdmin->assignRole('secretaire_general');
    $paiement = app(CotisationService::class)->declarer($this->jewrineFinance->membre, 'T_JFINANCE00000002', 1);

    app(PaiementService::class)->confirmer($paiement, $autreAdmin);
})->throws(DomainException::class, 'doit être validé par');

it('fait valider le paiement du Jewrine national par le Jewrine finance, et non par le Secrétaire général', function () {
    $service = app(CotisationService::class);

    $paiement = $service->declarer($this->jewrineNational->membre, 'T_JNATIONAL0000001', 1);
    app(PaiementService::class)->confirmer($paiement, $this->jewrineFinance);
    expect($paiement->fresh()->statut)->toBe(StatutPaiement::Confirme);

    $autre = $service->declarer($this->jewrineNational->membre, 'T_JNATIONAL0000002', 1);
    expect(fn () => app(PaiementService::class)->confirmer($autre, $this->secretaire))->toThrow(DomainException::class);
});

it('laisse le super administrateur valider en secours', function () {
    $paiement = app(CotisationService::class)->declarer($this->secretaire->membre, 'T_SECRETAIRE000001', 1);

    app(PaiementService::class)->confirmer($paiement, $this->super);

    expect($paiement->fresh()->statut)->toBe(StatutPaiement::Confirme);
});

it('applique aussi la règle aux paiements en espèces', function () {
    $autreAdmin = User::factory()->create();
    $autreAdmin->assignRole('secretaire_general');

    app(CotisationService::class)->enregistrerEspeces($this->jewrineFinance->membre, 1, $autreAdmin);
})->throws(DomainException::class);

it('réserve le statut des membres du bureau au super administrateur', function () {
    $fiche = $this->jewrineFinance->membre;

    expect($this->jewrineNational->can('changerStatut', [$fiche, StatutMembre::Suspendu]))->toBeFalse()
        ->and($this->super->can('changerStatut', [$fiche, StatutMembre::Suspendu]))->toBeTrue();
});

it('range les paiements du bureau dans un onglet à part', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $paiement = app(CotisationService::class)->declarer($this->jewrineFinance->membre, 'T_JFINANCE00000003', 1);

    $this->actingAs($this->secretaire);

    Livewire::test(ManagePaiements::class)
        ->assertCanNotSeeTableRecords([$paiement])
        ->set('activeTab', 'bureau')
        ->assertCanSeeTableRecords([$paiement]);
});

it('réserve l\'écran des règles au super administrateur', function () {
    $this->actingAs($this->super)->get('/admin/validation-bureau')->assertOk()->assertSee('Jewrine finance');
    $this->actingAs($this->jewrineNational)->get('/admin/validation-bureau')->assertForbidden();
});

it('exige, pour un payeur qui cumule deux postes, un validateur autorisé par les deux règles', function () {
    // Le Jewrine finance devient aussi Secrétaire général : ses paiements relèvent des deux règles.
    nommerPourLeTest($this->jewrineFinance, 'secretaire_general');
    $payeur = $this->jewrineFinance->fresh()->membre;
    $service = app(CotisationService::class);

    // Le Jewrine national est autorisé par les deux règles (« Jewrine finance » et « Secrétaire général »).
    $paiement = $service->declarer($payeur, 'T_CUMUL00000000001', 1);
    expect(RegleValidation::pour($payeur))->toHaveCount(2)
        ->and(RegleValidation::autorise($this->jewrineNational, $payeur))->toBeTrue()
        ->and(RegleValidation::validateursDe($payeur)->pluck('id')->all())->toBe([$this->jewrineNational->id]);

    app(PaiementService::class)->confirmer($paiement, $this->jewrineNational);
    expect($paiement->fresh()->statut)->toBe(StatutPaiement::Confirme);

    // Le Secrétaire général n'est autorisé que par la règle du Jewrine finance, pas par celle de son propre poste : refusé.
    $refuse = $service->declarer($payeur, 'T_CUMUL00000000002', 1);

    expect(fn () => app(PaiementService::class)->confirmer($refuse, $this->secretaire))->toThrow(DomainException::class, 'et par')
        ->and(fn () => app(PaiementService::class)->confirmer($refuse, $this->jewrineFinance->fresh()))->toThrow(DomainException::class, 'propre paiement');
});
