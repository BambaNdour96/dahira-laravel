<?php

use App\Filament\Resources\DemandesSuppression\Pages\ManageDemandesSuppression;
use App\Filament\Resources\Evenements\Pages\ManageEvenements;
use App\Filament\Resources\ReglesValidation\Pages\ManageReglesValidation;
use App\Filament\Resources\SessionsActives\Pages\ManageSessionsActives;
use App\Filament\Widgets\MesTaches;
use App\Models\Cotisation;
use App\Models\DemandeSuppression;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Poste;
use App\Models\RegleValidation;
use App\Models\SessionActive;
use App\Models\User;
use App\Services\CarteService;
use App\Services\CotisationService;
use App\Services\PresenceService;
use Database\Seeders\PostesSeeder;
use Database\Seeders\ReglesValidationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function compteAvecPoste(string $poste, array $roles): User
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
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole(['super_administrateur', 'administrateur']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('administrateur');
});

describe('les événements', function () {
    beforeEach(function () {
        $this->actingAs($this->super);
        $this->evenement = Evenement::query()->create(['titre' => 'Magal 2026', 'lieu' => 'Kaolack', 'debut' => now()->addHour()]);
    });

    it('affiche la liste et ouvre le formulaire de création', function () {
        Livewire::test(ManageEvenements::class)
            ->assertCanSeeTableRecords([$this->evenement])
            ->mountAction('create')
            ->assertActionMounted('create');
    });

    it('exporte la liste des présents', function () {
        $membre = Membre::factory()->valide()->create(['matricule' => 'NDK-2026-0042']);
        app(CarteService::class)->emettre($membre);
        app(PresenceService::class)->marquer($this->evenement, $membre, $this->super);

        $contenu = $this->get(route('evenements.presences.export', $this->evenement))->assertOk()->streamedContent();

        expect($contenu)->toContain('Matricule')->and($contenu)->toContain('NDK-2026-0042');
    });

    it('réserve les événements au super administrateur', function () {
        expect($this->super->can('view', $this->evenement))->toBeTrue()
            ->and($this->super->can('update', $this->evenement))->toBeTrue()
            ->and($this->super->can('delete', $this->evenement))->toBeTrue()
            ->and($this->admin->can('update', $this->evenement))->toBeFalse()
            ->and($this->admin->can('delete', $this->evenement))->toBeFalse();
    });
});

it('modifie une règle de validation sans jamais laisser un poste se valider lui-même', function () {
    $this->actingAs($this->super);

    $jewrineFinance = Poste::query()->where('nom', 'Jewrine finance')->value('id');
    $secretaire = Poste::query()->where('nom', 'Secrétaire général')->value('id');
    $regle = RegleValidation::query()->where('poste_id', $jewrineFinance)->firstOrFail();

    Livewire::test(ManageReglesValidation::class)
        ->assertCanSeeTableRecords([$regle])
        ->mountAction('create')
        ->assertActionMounted('create');

    Livewire::test(ManageReglesValidation::class)
        ->callAction(TestAction::make('edit')->table($regle), data: ['validateurs' => [$secretaire, $jewrineFinance]])
        ->assertHasNoActionErrors();

    expect($regle->fresh()->idsValidateurs())->toBe([(int) $secretaire]);
});

it('montre aux validateurs les paiements du bureau qui les attendent', function () {
    $secretaire = compteAvecPoste('Secrétaire général', ['administrateur']);
    $jewrineFinance = compteAvecPoste('Jewrine finance', ['tresorier']);

    app(CotisationService::class)->declarer($jewrineFinance->membre, 'T_COUVERTURE000001', 1);

    $this->actingAs($secretaire);

    Livewire::test(MesTaches::class)->assertSee('Paiements du bureau à valider');
});

it('refuse une demande de suppression avec un motif', function () {
    $compte = User::factory()->create();
    $demande = DemandeSuppression::query()->create(['user_id' => $compte->id, 'statut' => DemandeSuppression::EN_ATTENTE]);

    $this->actingAs($this->super);

    Livewire::test(ManageDemandesSuppression::class)
        ->callAction(TestAction::make('refuserDemande')->table($demande), data: ['reponse' => 'Cotisations à régulariser'])
        ->assertHasNoActionErrors();

    expect($demande->fresh()->statut)->toBe(DemandeSuppression::REFUSEE)
        ->and($demande->fresh()->reponse)->toBe('Cotisations à régulariser');
});

it('déconnecte une session depuis l\'écran des connectés', function () {
    $membre = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'session-a-couper', 'user_id' => $membre->id, 'ip_address' => '41.82.0.2',
        'user_agent' => 'Mozilla/5.0 Firefox/130', 'payload' => '', 'last_activity' => now()->getTimestamp(),
    ]);

    $this->actingAs($this->super);

    Livewire::test(ManageSessionsActives::class)
        ->callAction(TestAction::make('deconnecter')->table(SessionActive::query()->findOrFail('session-a-couper')))
        ->assertHasNoActionErrors();

    expect(DB::table('sessions')->where('id', 'session-a-couper')->exists())->toBeFalse();
});

it('présente la page de suppression du compte, puis la demande en cours', function () {
    $compte = User::factory()->create(['password' => 'motdepasse1']);
    $compte->assignRole('membre');
    Membre::factory()->for($compte)->valide()->create();

    $this->actingAs($compte)->get('/mon-compte/suppression')->assertOk()->assertSee('Envoyer ma demande de suppression');

    DemandeSuppression::query()->create(['user_id' => $compte->id, 'statut' => DemandeSuppression::EN_ATTENTE]);

    $this->actingAs($compte)->get('/mon-compte/suppression')->assertOk()->assertSee('en cours de traitement');
});

it('relie chaque cotisation à son membre et à son paiement', function () {
    $tresorier = User::factory()->create();
    $tresorier->assignRole('tresorier');
    $membre = Membre::factory()->valide()->create(['valide_le' => now()->subMonths(2)]);

    $paiement = app(CotisationService::class)->enregistrerEspeces($membre, 1, $tresorier);
    $cotisation = Cotisation::query()->firstOrFail();

    expect($cotisation->membre->id)->toBe($membre->id)
        ->and($cotisation->paiement?->id)->toBe($paiement->id);
});
