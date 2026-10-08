<?php

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Filament\Resources\Paiements\Pages\ManagePaiements;
use App\Models\Membre;
use App\Models\Poste;
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

    Membre::factory()->for($compte)->valide()->create([
        'poste_id' => Poste::query()->where('nom', $poste)->value('id'),
        'valide_le' => now()->subMonths(2),
    ]);

    return $compte->fresh();
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, PostesSeeder::class, ReglesValidationSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);

    $this->jewrineFinance = compteDuBureauAvecPoste('Jewrine finance', ['tresorier']);
    $this->secretaire = compteDuBureauAvecPoste('Secrétaire général', ['administrateur']);
    $this->jewrineNational = compteDuBureauAvecPoste('Jewrine national', ['administrateur']);

    $this->super = User::factory()->create();
    $this->super->assignRole(['super_administrateur', 'administrateur']);
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
    $autreAdmin->assignRole('administrateur');
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
    $autreAdmin->assignRole('administrateur');

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
