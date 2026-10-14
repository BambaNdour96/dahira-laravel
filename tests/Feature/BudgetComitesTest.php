<?php

use App\Enums\ModeCollecte;
use App\Enums\ProgrammeCollecte;
use App\Enums\StatutCollecte;
use App\Filament\Resources\BesoinsCommission\Pages\ListBesoinsCommission;
use App\Filament\Resources\DepensesCommission\Pages\ListDepensesCommission;
use App\Filament\Resources\Instances\Widgets\BudgetCommissions;
use App\Filament\Resources\Instances\Widgets\TableauDeBordComite;
use App\Models\BesoinCommission;
use App\Models\Collecte;
use App\Models\CommissionComite;
use App\Models\DepenseCommission;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\User;
use App\Services\BudgetComiteService;
use App\Services\CollecteService;
use App\Services\ComiteService;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function membreBudget(string $prenom): Membre
{
    $compte = User::factory()->create(['name' => $prenom]);
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->valide()->create(['prenom' => $prenom]);
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('local');

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');

    $this->president = membreBudget('Ousmane');
    $this->chef = membreBudget('Fatou');
    app(ComiteService::class)->definirComiteDirecteur([$this->president->id], $this->super);

    $this->collecte = Collecte::query()->create([
        'titre' => 'Magal 2026', 'programme' => ProgrammeCollecte::Magal, 'mode' => ModeCollecte::EngagementLibre,
        'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte,
    ]);
    $this->cdgmt = Instance::query()->create([
        'type' => Instance::CDGMT, 'nom' => 'CDGMT 2026', 'code' => 'CDGMT', 'annee' => 2026,
        'president_id' => $this->president->id, 'collecte_id' => $this->collecte->id,
    ]);
    $this->cuisine = CommissionComite::query()->create(['instance_id' => $this->cdgmt->id, 'nom' => 'Cuisine', 'chef_id' => $this->chef->id]);
    app(ComiteService::class)->integrerResponsables($this->cdgmt);

    $this->compteChef = User::query()->findOrFail($this->chef->user_id);
    $this->comptePresident = User::query()->findOrFail($this->president->user_id);
    $this->service = app(BudgetComiteService::class);
});

it('fait exprimer les besoins par le chef, et retenir par le président pour former le budget', function () {
    $this->actingAs($this->compteChef)->get(route('comites.index'))->assertSee('Je dirige')->assertSee('Exprimer un besoin');
    $this->actingAs($this->compteChef)->post(route('comites.besoins.store', $this->cuisine), ['designation' => 'Sacs de riz', 'quantite' => 10, 'prix_unitaire' => 25000])
        ->assertSessionHas('comite_succes');
    $this->actingAs($this->compteChef)->post(route('comites.besoins.store', $this->cuisine), ['designation' => 'Viande', 'quantite' => 1, 'prix_unitaire' => 300000]);

    $etranger = User::query()->findOrFail(membreBudget('Ibou')->user_id);
    $this->actingAs($etranger)->post(route('comites.besoins.store', $this->cuisine), ['designation' => 'Gâteaux', 'quantite' => 1, 'prix_unitaire' => 1000])
        ->assertSessionHas('comite_erreur');

    [$riz, $viande] = BesoinCommission::query()->orderBy('id')->get()->all();
    $this->actingAs($this->comptePresident);
    Livewire::test(ListBesoinsCommission::class)
        ->callAction(TestAction::make('retenir')->table($riz))
        ->callAction(TestAction::make('ecarter')->table($viande), ['motif' => 'Offerte par un fidèle'])
        ->assertHasNoActionErrors();

    expect($this->cuisine->budget())->toBe(250000)
        ->and($viande->fresh()->statut)->toBe('ecarte');

    $suivant = Instance::query()->create(['type' => Instance::CDGMT, 'nom' => 'CDGMT 2027', 'code' => 'CDGMT', 'annee' => 2027, 'president_id' => $this->president->id]);
    app(ComiteService::class)->reprendreCommissions($suivant);
    expect(BesoinCommission::query()->whereHas('commission', fn ($query) => $query->where('instance_id', $suivant->id))->pluck('statut')->all())->toBe(['propose']);
});

it('fait déclarer les dépenses avec justificatif, valider par le Jewrine finance, et alerte en cas de dépassement', function () {
    BesoinCommission::query()->create(['commission_id' => $this->cuisine->id, 'designation' => 'Riz', 'quantite' => 1, 'prix_unitaire' => 100000, 'statut' => 'retenu']);

    $this->actingAs($this->compteChef)->post(route('comites.depenses.store', $this->cuisine), [
        'objet' => '10 sacs de riz', 'montant' => 120000, 'date_depense' => now()->format('Y-m-d'), 'fournisseur' => 'Marché central',
        'justificatif' => UploadedFile::fake()->image('facture.jpg'),
    ])->assertSessionHas('comite_succes');
    $this->actingAs($this->compteChef)->post(route('comites.depenses.store', $this->cuisine), [
        'objet' => 'Bois de cuisson', 'montant' => 15000, 'date_depense' => now()->format('Y-m-d'),
    ])->assertSessionHas('comite_succes');

    [$riz, $bois] = DepenseCommission::query()->orderBy('id')->get()->all();
    Storage::disk('local')->assertExists((string) $riz->justificatif_path);
    expect($this->tresorier->fresh()->notifications()->count())->toBe(2);

    $this->actingAs($this->tresorier)->get(route('justificatifs.show', $riz))->assertOk();
    $this->actingAs(User::query()->findOrFail(membreBudget('Ibou')->user_id))->get(route('justificatifs.show', $riz))->assertForbidden();

    $this->actingAs($this->tresorier);
    Livewire::test(ListDepensesCommission::class)
        ->callAction(TestAction::make('valider')->table($riz))
        ->callAction(TestAction::make('refuser')->table($bois), ['motif' => 'Facture manquante'])
        ->assertHasNoActionErrors();

    expect($riz->fresh()->statut)->toBe('validee')
        ->and($bois->fresh()->statut)->toBe('refusee')
        ->and($this->cuisine->depense())->toBe(120000)
        ->and($this->comptePresident->fresh()->notifications()->where('data', 'like', '%Budget d%pass%')->exists())->toBeTrue()
        ->and($this->compteChef->fresh()->notifications()->count())->toBe(1)
        ->and(fn () => $this->service->valider($riz->fresh(), $this->tresorier))->toThrow(DomainException::class);
});

it('calcule le solde du Magal et produit son bilan', function () {
    $donateur = membreBudget('Awa');
    app(CollecteService::class)->fixerParMembre($this->collecte, $donateur, 200000);
    app(CollecteService::class)->enregistrerEspeces($this->collecte, $donateur, 200000, $this->tresorier);
    BesoinCommission::query()->create(['commission_id' => $this->cuisine->id, 'designation' => 'Riz', 'quantite' => 1, 'prix_unitaire' => 150000, 'statut' => 'retenu']);
    DepenseCommission::query()->create(['commission_id' => $this->cuisine->id, 'objet' => 'Riz', 'montant' => 120000, 'date_depense' => now(), 'statut' => 'validee']);

    expect($this->service->recapitulatif($this->cdgmt))->toMatchArray(['budget' => 150000, 'depenses' => 120000, 'recettes' => 200000, 'solde' => 80000]);

    $this->actingAs($this->comptePresident);
    Livewire::test(TableauDeBordComite::class, ['record' => $this->cdgmt])->assertSee('Solde')->assertSee('80 000 FCFA');
    Livewire::test(BudgetCommissions::class, ['record' => $this->cdgmt])->assertSee('Cuisine')->assertSee('80 %');

    $this->get(route('comites.bilan', $this->cdgmt))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs(User::query()->findOrFail(membreBudget('Ibou')->user_id))->get(route('comites.bilan', $this->cdgmt))->assertForbidden();
});
