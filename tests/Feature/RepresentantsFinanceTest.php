<?php

use App\Enums\ModeCollecte;
use App\Enums\ProgrammeCollecte;
use App\Enums\Sexe;
use App\Enums\StatutCollecte;
use App\Enums\StatutPaiement;
use App\Filament\Resources\RepresentantsFinance\Pages\ManageRepresentantsFinance;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\RemiseFonds;
use App\Models\RepresentantFinance;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CollecteService;
use App\Services\CotisationService;
use App\Services\PaiementService;
use App\Services\RepresentantFinanceService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function membreFinance(Cellule $cellule, Sexe $sexe, string $prenom, int $moisDeRetard = 3): Membre
{
    $compte = User::factory()->create();
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->for($cellule)->valide()->create([
        'prenom' => $prenom,
        'sexe' => $sexe,
        'valide_le' => now()->startOfMonth()->subMonths($moisDeRetard),
    ]);
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->national = User::factory()->create(['name' => 'Fatou Ndiaye']);
    $this->national->assignRole('tresorier');

    $this->kaolack = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->dakar = Cellule::factory()->create(['nom' => 'Dakar']);

    $this->representanteMembre = membreFinance($this->kaolack, Sexe::Femme, 'Khady');
    $this->representante = User::query()->findOrFail($this->representanteMembre->user_id);
    $this->sokhna = membreFinance($this->kaolack, Sexe::Femme, 'Awa');
    $this->goorYalla = membreFinance($this->kaolack, Sexe::Homme, 'Moussa');
    $this->sokhnaDakar = membreFinance($this->dakar, Sexe::Femme, 'Aïda');

    $this->service = app(RepresentantFinanceService::class);
    $this->service->designer($this->kaolack, 'femmes', $this->representante, $this->national);
});

it('fait désigner par le Jewrine finance une Sokhna pour les Sokhnas de la cellule', function () {
    expect($this->representante->fresh()->hasRole('representant_finance'))->toBeTrue()
        ->and(RepresentantFinance::pour($this->sokhna)?->user_id)->toBe($this->representante->id)
        ->and(RepresentantFinance::pour($this->goorYalla))->toBeNull();

    $goorYallaCompte = User::query()->findOrFail($this->goorYalla->user_id);
    expect(fn () => $this->service->designer($this->kaolack, 'femmes', $goorYallaCompte, $this->national))->toThrow(DomainException::class)
        ->and(fn () => $this->service->designer($this->kaolack, 'hommes', $goorYallaCompte, $this->representante))->toThrow(DomainException::class);
});

it('prévient la représentante et la laisse valider un paiement de son périmètre seulement', function () {
    Notification::fake();
    $cotisation = app(CotisationService::class)->declarer($this->sokhna, 'T_REPRESENTANTE0001', 1);
    Notification::assertSentTo($this->representante, NotificationMembre::class);

    $this->actingAs($this->representante)->get(route('caisse.index'))->assertOk()->assertSee('Awa')->assertSee('T_REPRESENTANTE0001');
    $this->actingAs($this->representante)->post(route('caisse.valider', $cotisation))->assertSessionHas('caisse_succes');

    expect($cotisation->fresh()->statut)->toBe(StatutPaiement::Confirme)
        ->and($cotisation->fresh()->traite_par)->toBe($this->representante->id);

    $horsPerimetre = app(CotisationService::class)->declarer($this->goorYalla, 'T_REPRESENTANTE0002', 1);
    $autreCellule = app(CotisationService::class)->declarer($this->sokhnaDakar, 'T_REPRESENTANTE0003', 1);

    expect(fn () => $this->service->valider($this->representante, $horsPerimetre))->toThrow(DomainException::class)
        ->and(fn () => $this->service->valider($this->representante, $autreCellule))->toThrow(DomainException::class);

    app(PaiementService::class)->confirmer($horsPerimetre, $this->national);
    expect($horsPerimetre->fresh()->statut)->toBe(StatutPaiement::Confirme);
});

it('encaisse les espèces des cotisations, des Sass et des Jàyante, puis remet la caisse au national', function () {
    $collecte = Collecte::query()->create([
        'titre' => 'Magal 2026', 'programme' => ProgrammeCollecte::Magal, 'mode' => ModeCollecte::EngagementLibre,
        'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte,
    ]);
    $engagement = app(CollecteService::class)->fixerParMembre($collecte, $this->sokhna, 10000);

    $this->actingAs($this->representante)->get(route('caisse.membre', $this->sokhna))->assertOk()->assertSee('Jàyante')->assertSee('Magal 2026');
    $this->actingAs($this->representante)->get(route('caisse.membre', $this->goorYalla))->assertForbidden();

    $this->actingAs($this->representante)->post(route('caisse.cotisation', $this->sokhna), ['nombre_mois' => 2])->assertSessionHas('caisse_succes');
    $this->actingAs($this->representante)->post(route('caisse.versement', [$this->sokhna, $engagement]), ['montant' => 4000])->assertSessionHas('caisse_succes');
    $this->actingAs($this->representante)->post(route('caisse.versement', [$this->sokhna, $engagement]), ['montant' => 9000])->assertSessionHas('caisse_erreur');

    $enCaisse = $this->service->enCaisse($this->representante);
    expect($enCaisse)->toBe(2 * app(CotisationService::class)->montantMensuel() + 4000);

    $this->actingAs($this->national);
    Livewire::test(ManageRepresentantsFinance::class)
        ->assertSee($this->representante->name)
        ->callAction(TestAction::make('remise')->table(RepresentantFinance::query()->firstOrFail()))
        ->assertHasNoActionErrors()
        ->assertRedirectContains('/remises/');

    expect($this->service->enCaisse($this->representante))->toBe(0)
        ->and(Paiement::query()->whereNotNull('remise_id')->count())->toBe(2);

    $remise = RemiseFonds::query()->firstOrFail();
    expect($remise->numero)->toBe('REM-'.now()->format('Y').'-0001')->and($remise->montant)->toBe($enCaisse);

    $this->get(route('remises.bordereau', $remise))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($this->representante)->get(route('remises.bordereau', $remise))->assertOk();
    $this->actingAs(User::query()->findOrFail($this->sokhna->user_id))->get(route('remises.bordereau', $remise))->assertForbidden();
});

it('refuse la caisse aux simples membres et alerte le national chaque matin', function () {
    $this->actingAs(User::query()->findOrFail($this->goorYalla->user_id))->get(route('caisse.index'))->assertForbidden();

    $ancien = app(CotisationService::class)->declarer($this->sokhna, 'T_REPRESENTANTE0009', 1);
    $ancien->forceFill(['declare_le' => now()->subDays(3)])->save();

    expect($this->service->routineDuMatin()['en_attente'])->toBe(1)
        ->and($this->national->fresh()->notifications()->where('data', 'like', '%Tr%sorerie des cellules%')->exists())->toBeTrue();

    $this->artisan('finance:routine')->assertSuccessful();
});
