<?php

use App\Enums\StatutMembre;
use App\Filament\Widgets\EncaissementsParCellule;
use App\Filament\Widgets\EncaissementsParMois;
use App\Filament\Widgets\RapportFinancier;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\User;
use App\Services\CotisationService;
use App\Services\PaiementService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);

    $this->cotisations = app(CotisationService::class);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
});

function membreQuiCotise(int $moisEcoules = 3): Membre
{
    $user = User::factory()->create();
    $user->assignRole('membre');

    return Membre::factory()->for($user)->valide()->create([
        'valide_le' => now()->startOfMonth()->subMonths($moisEcoules),
    ]);
}

it('numérote les reçus à la suite', function () {
    $premier = $this->cotisations->enregistrerEspeces(membreQuiCotise(), 1, $this->tresorier);
    $second = $this->cotisations->enregistrerEspeces(membreQuiCotise(), 1, $this->tresorier);

    $annee = now()->format('Y');

    expect($premier->numero_recu)->toBe("COT-{$annee}-0001")
        ->and($second->numero_recu)->toBe("COT-{$annee}-0002");
});

it('attribue un reçu à la confirmation d\'une déclaration Wave', function () {
    $paiement = $this->cotisations->declarer(membreQuiCotise(), 'T_RECU1', 1);
    expect($paiement->numero_recu)->toBeNull();

    app(PaiementService::class)->confirmer($paiement, $this->tresorier);

    expect($paiement->fresh()->numero_recu)->not->toBeNull();
});

it('laisse le membre et le Jewrine finance télécharger le reçu, mais pas un autre membre', function () {
    $membre = membreQuiCotise();
    $paiement = $this->cotisations->enregistrerEspeces($membre, 1, $this->tresorier);

    $this->actingAs($membre->user)->get(route('recus.show', $paiement))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($this->tresorier)->get(route('recus.show', $paiement))->assertOk();

    $this->actingAs(membreQuiCotise()->user)->get(route('recus.show', $paiement))->assertForbidden();
});

it('ne délivre pas de reçu pour un paiement non confirmé', function () {
    $membre = membreQuiCotise();
    $paiement = $this->cotisations->declarer($membre, 'T_ENATTENTE', 1);

    $this->actingAs($membre->user)->get(route('recus.show', $paiement))->assertNotFound();
});

it('compte les mois dispensés comme réglés', function () {
    $membre = membreQuiCotise(3);

    $this->cotisations->exonerer($membre, 2, 'Maladie');

    expect($this->cotisations->moisEnRetard($membre))->toBe([])
        ->and(Cotisation::query()->where('exoneree', true)->count())->toBe(2);
});

it('exige un motif pour une dispense', function () {
    $this->cotisations->exonerer(membreQuiCotise(), 1, '');
})->throws(DomainException::class);

it('affiche le rapport financier au Jewrine finance', function () {
    $this->cotisations->enregistrerEspeces(membreQuiCotise(), 3, $this->tresorier);

    $this->actingAs($this->tresorier);

    Livewire::test(RapportFinancier::class)
        ->assertSee('Encaissé cette année')
        ->assertSee('3 000 FCFA');

    Livewire::test(EncaissementsParMois::class)->assertHasNoErrors();
    Livewire::test(EncaissementsParCellule::class)->assertHasNoErrors();
});

it('cache le rapport financier aux Jewrine de cellule', function () {
    $jewrine = User::factory()->create();
    $jewrine->assignRole('jewrine_cellule');

    $this->actingAs($jewrine);

    expect(RapportFinancier::canView())->toBeFalse();
});

it('garde la dispense pour le seul super administrateur', function () {
    $membre = membreQuiCotise();

    expect($membre->statut)->toBe(StatutMembre::Valide);

    $this->actingAs($this->super)->get('/admin/membres')->assertOk()->assertSee('Dispenser de cotisation');
    $this->actingAs($this->tresorier)->get('/admin/membres')->assertOk()->assertDontSee('Dispenser de cotisation');
});
