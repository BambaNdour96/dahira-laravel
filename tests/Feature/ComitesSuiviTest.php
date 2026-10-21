<?php

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Filament\Resources\ActionsComite\Pages\ListActionsComite;
use App\Filament\Resources\Decisions\Pages\ListDecisions;
use App\Filament\Resources\Instances\InstanceResource;
use App\Filament\Resources\Instances\Pages\EditInstance;
use App\Filament\Resources\Instances\Pages\ViewInstance;
use App\Filament\Resources\Instances\Widgets\AvancementCommissions;
use App\Filament\Resources\Instances\Widgets\TableauDeBordComite;
use App\Filament\Resources\Reunions\Pages\ViewReunion;
use App\Models\ActionComite;
use App\Models\Collecte;
use App\Models\CommissionComite;
use App\Models\Decision;
use App\Models\Evenement;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\Reunion;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\ComiteService;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function membreSuivi(string $prenom): Membre
{
    $compte = User::factory()->create(['name' => $prenom]);
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->valide()->create(['prenom' => $prenom]);
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->national = User::factory()->create();
    $this->national->assignRole('jewrine_national');

    $this->president = membreSuivi('Ousmane');
    $this->chef = membreSuivi('Fatou');
    $this->aide = membreSuivi('Aïssatou');
    $this->service = app(ComiteService::class);
    $this->service->definirComiteDirecteur([$this->president->id], $this->super);

    $jourJ = Evenement::query()->create(['titre' => 'Magal 2026', 'lieu' => 'Touba', 'debut' => now()->addDays(20)]);
    $collecte = Collecte::query()->create([
        'titre' => 'Magal 2026', 'type_programme_id' => typeProgramme('magal'), 'mode' => ModeCollecte::Jayante,
        'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte, 'evenement_id' => $jourJ->id,
    ]);

    $this->cdgmt = Instance::query()->create([
        'type' => Instance::CDGMT, 'nom' => 'CDGMT 2026', 'code' => 'CDGMT', 'annee' => 2026,
        'president_id' => $this->president->id, 'collecte_id' => $collecte->id,
    ]);
    $this->cuisine = CommissionComite::query()->create(['instance_id' => $this->cdgmt->id, 'nom' => 'Cuisine', 'chef_id' => $this->chef->id]);
    $this->service->integrerResponsables($this->cdgmt);

    $this->reunion = Reunion::query()->create([
        'instance_id' => $this->cdgmt->id, 'titre' => 'Réunion n° 1', 'debut' => now()->subDay(), 'lieu' => 'Kaolack', 'secretaire_id' => $this->president->id,
    ]);
});

it('ajoute les membres d\'une commission au comité, et garde le CDGMT modifiable', function () {
    $this->actingAs($this->national);

    $this->cuisine->membres()->attach($this->aide->id);
    Livewire::test(EditInstance::class, ['record' => $this->cdgmt->getRouteKey()])
        ->fillForm(['nom' => 'CDGMT – Grand Magal de Touba 2026'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->cdgmt->fresh()->nom)->toBe('CDGMT – Grand Magal de Touba 2026')
        ->and($this->cdgmt->membres()->whereKey($this->aide->id)->exists())->toBeTrue()
        ->and(InstanceResource::canEdit(Instance::comiteDirecteur()))->toBeFalse();
});

it('fait avancer une action par le chef de sa commission, depuis son téléphone', function () {
    $action = ActionComite::query()->create(['reunion_id' => $this->reunion->id, 'description' => 'Commander 40 bols', 'commission_id' => $this->cuisine->id, 'echeance' => now()->subDays(2)]);
    $chef = User::query()->findOrFail($this->chef->user_id);

    expect($action->estEnRetard())->toBeTrue()->and($action->compteResponsable()?->id)->toBe($chef->id);

    $this->actingAs($chef)->get(route('comites.index'))->assertSee('Commander 40 bols')->assertSee('En retard');
    $this->actingAs($chef)->post(route('comites.actions.statut', $action), ['statut' => 'faite'])->assertSessionHas('comite_succes');
    expect($action->fresh()->statut)->toBe('faite');

    $etranger = User::query()->findOrFail($this->aide->user_id);
    $this->actingAs($etranger)->post(route('comites.actions.statut', $action), ['statut' => 'a_faire'])->assertSessionHas('comite_erreur');

    $this->actingAs($this->national);
    Livewire::test(ListActionsComite::class)
        ->assertSee('Commander 40 bols')
        ->callAction(TestAction::make('statut')->table($action), ['statut' => 'en_cours'])
        ->assertHasNoActionErrors()
        ->filterTable('en_retard')
        ->assertCanSeeTableRecords([$action->fresh()]);
});

it('rappelle les échéances au responsable et signale les retards au président', function () {
    Notification::fake();
    ActionComite::query()->create(['reunion_id' => $this->reunion->id, 'description' => 'Louer les marmites', 'commission_id' => $this->cuisine->id, 'echeance' => now()]);
    ActionComite::query()->create(['reunion_id' => $this->reunion->id, 'description' => 'Réserver les cars', 'responsable_id' => $this->aide->id, 'echeance' => now()->subDay()]);

    expect($this->service->routineDuMatin())->toBe(['rappels' => 1, 'retards' => 1]);
    Notification::assertSentTo(User::query()->findOrFail($this->chef->user_id), NotificationMembre::class);
    Notification::assertSentTo(User::query()->findOrFail($this->president->user_id), NotificationMembre::class);
    $this->artisan('comites:routine')->assertSuccessful();
});

it('affiche le tableau de bord du CDGMT : compte à rebours, actions, commissions', function () {
    ActionComite::query()->create(['reunion_id' => $this->reunion->id, 'description' => 'Commander 40 bols', 'commission_id' => $this->cuisine->id, 'statut' => 'faite']);
    $this->actingAs($this->national);

    expect($this->service->avancement($this->cdgmt))->toMatchArray(['actions' => 1, 'faites' => 1, 'taux' => 100, 'jours' => 20]);
    Livewire::test(TableauDeBordComite::class, ['record' => $this->cdgmt])->assertSee('J-20')->assertSee('Collecte liée');
    Livewire::test(AvancementCommissions::class, ['record' => $this->cdgmt])->assertSee('Cuisine')->assertSee('Fatou');
    Livewire::test(ViewInstance::class, ['record' => $this->cdgmt->getRouteKey()])->assertOk();
});

it('affiche les présents sur les membres sans seuil, change l\'état d\'une décision et refuse une reprise impossible', function () {
    $this->actingAs($this->national);
    Livewire::test(ViewReunion::class, ['record' => $this->reunion->getRouteKey()])->assertSee('présent(s) sur')->assertDontSee('quorum');

    $decision = Decision::query()->create(['reunion_id' => $this->reunion->id, 'texte' => 'Budget de 1 500 000 FCFA']);
    Livewire::test(ListDecisions::class)
        ->callAction(TestAction::make('etat')->table($decision), ['etat' => 'annulee'])
        ->assertHasNoActionErrors();
    expect($decision->fresh()->etat)->toBe('annulee');

    expect(fn () => $this->service->reprendreCommissions($this->cdgmt))->toThrow(DomainException::class)
        ->and(ComiteService::peutGerer($this->national, Instance::comiteDirecteur()))->toBeFalse()
        ->and(ComiteService::peutOrganiser($this->national, Instance::comiteDirecteur()))->toBeTrue();
});
