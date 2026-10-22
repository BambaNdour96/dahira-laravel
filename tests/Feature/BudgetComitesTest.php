<?php

use App\Enums\ModeCollecte;
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
        'titre' => 'Magal 2026', 'type_programme_id' => typeProgramme('magal'), 'mode' => ModeCollecte::Jayante,
        'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte,
    ]);
    $this->cdgmt = Instance::query()->create([
        'type' => Instance::COMITE_ORGANISATION, 'nom' => 'CDGMT 2026', 'code' => 'CDGMT', 'annee' => 2026,
        'president_id' => $this->president->id, 'collecte_id' => $this->collecte->id,
    ]);
    $this->cuisine = CommissionComite::query()->create(['instance_id' => $this->cdgmt->id, 'nom' => 'Cuisine', 'chef_id' => $this->chef->id]);
    app(ComiteService::class)->integrerResponsables($this->cdgmt);

    $this->compteChef = User::query()->findOrFail($this->chef->user_id);
    $this->comptePresident = User::query()->findOrFail($this->president->user_id);
    $this->service = app(BudgetComiteService::class);
});

it('fait exprimer les besoins par le chef, et retenir par l\'arbitre (ici le Jewrine national) pour former le budget', function () {
    $this->actingAs($this->compteChef)->get(route('comites.index'))->assertSee('Je dirige')->assertSee('Exprimer un besoin');
    $this->actingAs($this->compteChef)->post(route('comites.besoins.store', $this->cuisine), ['designation' => 'Sacs de riz', 'quantite' => 10, 'prix_unitaire' => 25000])
        ->assertSessionHas('comite_succes');
    $this->actingAs($this->compteChef)->post(route('comites.besoins.store', $this->cuisine), ['designation' => 'Viande', 'quantite' => 1, 'prix_unitaire' => 300000]);

    $etranger = User::query()->findOrFail(membreBudget('Ibou')->user_id);
    $this->actingAs($etranger)->post(route('comites.besoins.store', $this->cuisine), ['designation' => 'Gâteaux', 'quantite' => 1, 'prix_unitaire' => 1000])
        ->assertSessionHas('comite_erreur');

    [$riz, $viande] = BesoinCommission::query()->orderBy('id')->get()->all();
    // Sans comité de suivi des dépenses, le Jewrine national arbitre ; le président ne retient plus les besoins.
    $this->actingAs($this->comptePresident);
    Livewire::test(ListBesoinsCommission::class)->assertActionHidden(TestAction::make('retenir')->table($riz));
    $this->actingAs(nommerPourLeTest(User::query()->findOrFail(membreBudget('Serigne')->user_id), 'jewrine_national'));
    Livewire::test(ListBesoinsCommission::class)
        ->callAction(TestAction::make('retenir')->table($riz))
        ->callAction(TestAction::make('ecarter')->table($viande), ['motif' => 'Offerte par un fidèle'])
        ->assertHasNoActionErrors();

    expect($this->cuisine->budget())->toBe(250000)
        ->and($viande->fresh()->statut)->toBe('ecarte');

    $suivant = Instance::query()->create(['type' => Instance::COMITE_ORGANISATION, 'nom' => 'CDGMT 2027', 'code' => 'CDGMT', 'annee' => 2027, 'president_id' => $this->president->id]);
    app(ComiteService::class)->reprendreCommissions($suivant);
    expect(BesoinCommission::query()->whereHas('commission', fn ($query) => $query->where('instance_id', $suivant->id))->pluck('statut')->all())->toBe(['propose']);
});

it('garde les anciennes dépenses déclarées en consultation seulement, avec leur justificatif', function () {
    Storage::disk('local')->put('justificatifs/riz.jpg', 'photo');
    $riz = DepenseCommission::query()->create(['commission_id' => $this->cuisine->id, 'objet' => '10 sacs de riz', 'montant' => 120000, 'date_depense' => now(),
        'statut' => 'validee', 'justificatif_path' => 'justificatifs/riz.jpg']);
    DepenseCommission::query()->create(['commission_id' => $this->cuisine->id, 'objet' => 'Bois', 'montant' => 15000, 'date_depense' => now(), 'statut' => 'declaree']);

    $this->actingAs($this->tresorier);
    Livewire::test(ListDepensesCommission::class)
        ->assertCanSeeTableRecords([$riz])
        ->assertActionDoesNotExist(TestAction::make('valider')->table($riz));
    $this->get(route('justificatifs.show', $riz))->assertOk();
    $this->actingAs(User::query()->findOrFail(membreBudget('Ibou')->user_id))->get(route('justificatifs.show', $riz))->assertForbidden();

    expect($this->cuisine->depense())->toBe(120000);
    $this->actingAs($this->compteChef)->get(route('comites.index'))
        ->assertSee('Anciennes dépenses déclarées')->assertSee('Consultation seulement')->assertDontSee('Déclarer une dépense');
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
