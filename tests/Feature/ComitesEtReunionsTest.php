<?php

use App\Filament\Resources\Decisions\Pages\ListDecisions;
use App\Filament\Resources\Instances\Pages\CreateInstance;
use App\Filament\Resources\Instances\Pages\ViewInstance;
use App\Filament\Resources\Instances\Widgets\MembresInstance;
use App\Filament\Resources\Reunions\Pages\CreateReunion;
use App\Filament\Resources\Reunions\Pages\ViewReunion;
use App\Models\ActionComite;
use App\Models\CommissionComite;
use App\Models\Decision;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\Poste;
use App\Models\Reunion;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\ComiteService;
use App\Services\NominationService;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function membreComite(string $prenom): Membre
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

    $this->ousmane = membreComite('Ousmane');
    $this->modou = membreComite('Modou');
    $this->fatou = membreComite('Fatou');
    $this->service = app(ComiteService::class);
    $this->service->definirComiteDirecteur([$this->ousmane->id, $this->modou->id], $this->super);
});

it('fait tenir la liste du Comité directeur par le seul super administrateur', function () {
    $ousmane = User::query()->findOrFail($this->ousmane->user_id);

    expect($ousmane->hasRole('comite_directeur'))->toBeTrue()
        ->and($ousmane->canAccessPanel(Filament::getPanel('admin')))->toBeTrue()
        ->and(User::query()->findOrFail($this->fatou->user_id)->hasRole('comite_directeur'))->toBeFalse()
        ->and(fn () => $this->service->definirComiteDirecteur([], $this->national))->toThrow(DomainException::class);

    $this->service->retirerDuComiteDirecteur($this->modou, $this->super);
    expect(User::query()->findOrFail($this->modou->user_id)->hasRole('comite_directeur'))->toBeFalse();

    $this->actingAs($this->super);
    Livewire::test(MembresInstance::class, ['record' => Instance::comiteDirecteur()])->assertSee('Ousmane')->assertActionVisible(TestAction::make('ajouter')->table());
    $this->actingAs($this->national);
    Livewire::test(MembresInstance::class, ['record' => Instance::comiteDirecteur()])->assertActionHidden(TestAction::make('ajouter')->table());
});

it('crée un CDGMT avec le Comité directeur, ses commissions et leurs chefs, même extérieurs', function () {
    $repeater = Repeater::fake();
    $this->actingAs($this->national);

    Livewire::test(CreateInstance::class)
        ->fillForm([
            'type' => Instance::CDGMT, 'nom' => 'CDGMT – Grand Magal de Touba 2026', 'code' => 'CDGMT', 'annee' => 2026,
            'president_id' => $this->ousmane->id, 'statut' => 'active',
            'commissions' => [
                ['nom' => 'Cuisine', 'mission' => 'Menus et service', 'chef_id' => $this->fatou->id],
                ['nom' => 'Hygiène et environnement', 'mission' => 'Bols, toilettes', 'chef_id' => $this->modou->id],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();
    $repeater();

    $cdgmt = Instance::query()->where('type', Instance::CDGMT)->firstOrFail();
    expect($cdgmt->membres()->pluck('membres.id')->sort()->values()->all())->toBe(collect([$this->ousmane->id, $this->modou->id, $this->fatou->id])->sort()->values()->all())
        ->and($cdgmt->commissions()->count())->toBe(2);

    $suivant = Instance::query()->create(['type' => Instance::CDGMT, 'nom' => 'CDGMT 2027', 'code' => 'CDGMT', 'annee' => 2027, 'president_id' => $this->modou->id]);
    expect($this->service->reprendreCommissions($suivant))->toBe(2)
        ->and($suivant->commissions()->whereNull('chef_id')->count())->toBe(2);

    Livewire::test(ViewInstance::class, ['record' => $cdgmt->getRouteKey()])->assertOk()->assertActionVisible('reunion');
});

it('tient une réunion de bout en bout : convocation, présences, décisions numérotées, actions et PV publié', function () {
    Notification::fake();
    $repeater = Repeater::fake();
    $cdgmt = Instance::query()->create(['type' => Instance::CDGMT, 'nom' => 'CDGMT 2026', 'code' => 'CDGMT', 'annee' => 2026, 'president_id' => $this->ousmane->id]);
    $cdgmt->membres()->sync([$this->ousmane->id, $this->modou->id, $this->fatou->id]);
    $cuisine = CommissionComite::query()->create(['instance_id' => $cdgmt->id, 'nom' => 'Cuisine', 'chef_id' => $this->fatou->id]);

    $this->actingAs(User::query()->findOrFail($this->ousmane->user_id));
    Livewire::test(CreateReunion::class)
        ->fillForm([
            'instance_id' => $cdgmt->id, 'titre' => 'Réunion n° 1', 'debut' => now()->addDay()->format('Y-m-d 20:00:00'),
            'lieu' => 'Kaolack', 'secretaire_id' => $this->modou->id,
            'points' => [['titre' => 'Budget de la cuisine'], ['titre' => 'Toilettes et bols']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();
    $repeater();

    $reunion = Reunion::query()->firstOrFail();
    expect($this->service->messageConvocation($reunion))->toContain('Budget de la cuisine');
    $this->service->convoquer($reunion);
    Notification::assertSentTo(User::query()->findOrFail($this->fatou->user_id), NotificationMembre::class);

    $this->service->enregistrerPresences($reunion, [$this->ousmane->id, $this->modou->id], [$this->fatou->id]);
    expect($this->service->quorum($reunion))->toBe(['presents' => 2, 'membres' => 3]);

    $premiere = Decision::query()->create(['reunion_id' => $reunion->id, 'texte' => 'Budget cuisine : 1 500 000 FCFA', 'issue' => 'jewrine_national']);
    $seconde = Decision::query()->create(['reunion_id' => $reunion->id, 'texte' => 'Location de 10 toilettes mobiles']);
    ActionComite::query()->create(['reunion_id' => $reunion->id, 'description' => 'Commander 40 bols', 'commission_id' => $cuisine->id, 'echeance' => now()->addWeek()]);

    expect($premiere->numero)->toBe('CDGMT-'.$reunion->debut->format('Y').'-D-001')
        ->and($seconde->numero)->toBe('CDGMT-'.$reunion->debut->format('Y').'-D-002')
        ->and($premiere->instance_id)->toBe($cdgmt->id);

    $secretaire = User::query()->findOrFail($this->modou->user_id);
    $this->actingAs($secretaire);
    Livewire::test(ViewReunion::class, ['record' => $reunion->getRouteKey()])
        ->assertActionVisible('publier')
        ->callAction('publier')
        ->assertHasNoActionErrors();

    expect($reunion->fresh()->estPubliee())->toBeTrue()
        ->and(ComiteService::peutRediger($secretaire, $reunion->fresh()))->toBeFalse()
        ->and($this->service->lienWhatsapp($reunion->fresh()))->toStartWith('https://wa.me/?text=');

    $this->actingAs(User::query()->findOrFail($this->fatou->user_id))->get(route('reunions.pv', $reunion))
        ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $etranger = membreComite('Ibou');
    $this->actingAs(User::query()->findOrFail($etranger->user_id))->get(route('reunions.pv', $reunion))->assertForbidden();

    $this->actingAs(User::query()->findOrFail($this->fatou->user_id))->get(route('comites.index'))
        ->assertOk()->assertSee('CDGMT 2026')->assertSee('Commander 40 bols')->assertSee('Lire le PV');

    $this->actingAs(User::query()->findOrFail($this->ousmane->user_id));
    Livewire::test(ListDecisions::class)->assertSee('CDGMT-'.$reunion->debut->format('Y').'-D-002');
});

it('laisse le Secrétaire général convoquer le Comité directeur, sans créer de comité d\'organisation', function () {
    $secretaire = nommerPourLeTest(membreComite('Astou')->user, 'secretaire_general');
    $comite = Instance::comiteDirecteur();

    expect(ComiteService::peutOrganiser($secretaire, $comite))->toBeTrue()
        ->and(ComiteService::peutCreer($secretaire))->toBeFalse()
        ->and(ComiteService::peutCreer($this->national))->toBeTrue();
});

it('fait désigner un convocateur du Comité directeur par le seul super administrateur', function () {
    $poste = Poste::query()->where('code', 'convocateur_comite_directeur')->firstOrFail();
    $delegue = membreComite('Pape');
    $nominations = app(NominationService::class);
    $nationalNomme = nommerPourLeTest(membreComite('Serigne')->user, 'jewrine_national');

    expect(fn () => $nominations->nommer($delegue->user, $poste, null, $nationalNomme))->toThrow(DomainException::class, 'Seul le super administrateur');

    $nominations->nommer($delegue->user, $poste, null, $this->super);

    expect(ComiteService::peutOrganiser($delegue->user->fresh(), Instance::comiteDirecteur()))->toBeTrue()
        ->and(ComiteService::peutCreer($delegue->user->fresh()))->toBeFalse();
});

it('note dans le PV si une décision est prise à l\'unanimité ou tranchée par le Jewrine national', function () {
    $reunion = Reunion::query()->create([
        'instance_id' => Instance::comiteDirecteur()->id, 'titre' => 'Réunion du bureau', 'debut' => now(), 'lieu' => 'Siège',
        'secretaire_id' => $this->ousmane->id,
    ]);
    $decision = Decision::query()->create(['reunion_id' => $reunion->id, 'texte' => 'Achat d\'une sono', 'issue' => 'jewrine_national']);

    expect($decision->libelleIssue())->toBe('Tranchée par le Jewrine national')
        ->and(Decision::query()->create(['reunion_id' => $reunion->id, 'texte' => 'Date du Magal'])->libelleIssue())->toBe('Unanimité');

    $this->actingAs($this->super)->get(route('reunions.pv', $reunion))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});
