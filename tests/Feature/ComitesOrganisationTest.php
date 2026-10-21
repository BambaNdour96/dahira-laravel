<?php

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Enums\TypeCaisse;
use App\Filament\Resources\Instances\InstanceResource;
use App\Filament\Resources\Instances\Pages\ViewInstance;
use App\Filament\Resources\Instances\RelationManagers\CommissionsRelationManager;
use App\Filament\Resources\Instances\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\Instances\RelationManagers\EngagementsRelationManager;
use App\Filament\Resources\Instances\RelationManagers\OrdresDepenseRelationManager;
use App\Filament\Resources\Instances\RelationManagers\ReunionsRelationManager;
use App\Filament\Resources\Instances\RelationManagers\TachesRelationManager;
use App\Filament\Resources\Instances\Widgets\IndicateursComite;
use App\Filament\Resources\Instances\Widgets\SuiviDepensesComite;
use App\Filament\Resources\Nominations\NominationResource;
use App\Models\ActionComite;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\CommissionComite;
use App\Models\Instance;
use App\Models\LibelleDepense;
use App\Models\Membre;
use App\Models\Nomination;
use App\Models\Poste;
use App\Models\PropositionSuiviDepenses;
use App\Models\Reunion;
use App\Models\RevisionBudget;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CollecteService;
use App\Services\ComiteService;
use App\Services\OrdreDepenseService;
use App\Services\ProgrammeService;
use App\Services\SuiviDepensesService;
use App\Services\TresorerieService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Un membre validé avec son compte. */
function membreOrganisation(Cellule $cellule, string $prenom): Membre
{
    $compte = User::factory()->create(['name' => $prenom]);
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->valide()->create(['cellule_id' => $cellule->id, 'prenom' => $prenom]);
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->kaolack = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->national = nommerPourLeTest(membreOrganisation($this->kaolack, 'Babacar')->user, 'jewrine_national');
    $this->tresorier = nommerPourLeTest(membreOrganisation($this->kaolack, 'Fatou')->user, 'jewrine_finance');
    $this->president = membreOrganisation($this->kaolack, 'Serigne');
    $this->chef = membreOrganisation($this->kaolack, 'Awa');
    $this->moussa = membreOrganisation($this->kaolack, 'Moussa');
    app(ComiteService::class)->definirComiteDirecteur([$this->president->id], $this->super);

    $programmes = app(ProgrammeService::class);
    $evenement = fn (string $titre, string $type): Collecte => $programmes->enregistrer([
        'titre' => $titre, 'type_programme_id' => typeProgramme($type), 'debut' => now()->addMonths(2)->format('Y-m-d 08:00'), 'lieu' => 'Touba',
        'mode' => ModeCollecte::Jayante->value, 'budget' => 1000000, 'organisation' => Collecte::COMITE, 'president_id' => $this->president->id,
        'ouverture' => now()->subDay()->toDateString(), 'statut' => StatutCollecte::Ouverte->value,
    ], null, $this->super);
    $this->magal = $evenement('Grand Magal 2026', 'magal');
    $this->ziar = $evenement('Grand Ziar 2026', 'ziar');
    $this->cdgmt = $this->magal->comite ?? throw new RuntimeException('Pas de comité.');
    $this->comiteZiar = $this->ziar->comite ?? throw new RuntimeException('Pas de comité.');
    $this->cuisine = CommissionComite::query()->create(['instance_id' => $this->cdgmt->id, 'nom' => 'Cuisine', 'chef_id' => $this->chef->id]);
    $this->transport = CommissionComite::query()->create(['instance_id' => $this->cdgmt->id, 'nom' => 'Transport', 'ordre' => 2]);
    $this->service = app(SuiviDepensesService::class);
});

it('donne le même espace au CDGMT et au Grand Ziar ; le suivi des dépenses est d\'office pour le Magal seulement', function () {
    expect($this->cdgmt->type)->toBe(Instance::COMITE_ORGANISATION)
        ->and($this->comiteZiar->type)->toBe(Instance::COMITE_ORGANISATION)
        ->and($this->cdgmt->estCdgmt())->toBeTrue()
        ->and($this->comiteZiar->estCdgmt())->toBeFalse()
        ->and($this->cdgmt->suivi_depenses)->toBeTrue()
        ->and($this->comiteZiar->suivi_depenses)->toBeFalse()
        ->and(fn () => $this->service->activer($this->comiteZiar, true, $this->president->user))->toThrow(DomainException::class, 'Jewrine national');

    $this->actingAs($this->national);
    Livewire::test(ViewInstance::class, ['record' => $this->comiteZiar->getRouteKey()])
        ->assertSee('Commissions et budget')->assertSee('Réunions et PV')->assertSee('Argent')->assertSee('Tâches')->assertSee('Documents')
        ->callAction(TestAction::make('activerSuivi'))
        ->assertHasNoActionErrors();
    expect($this->comiteZiar->fresh()->suivi_depenses)->toBeTrue();
    Livewire::test(SuiviDepensesComite::class, ['record' => $this->cdgmt])->assertSee('Actif')->assertSee('À nommer');
    $this->comiteZiar->fresh()?->update(['suivi_depenses' => false]);
    Livewire::test(SuiviDepensesComite::class, ['record' => $this->comiteZiar->fresh()])->assertSee('Non activé');

    // Le Comité directeur garde sa page, sans onglets d'événement.
    Livewire::test(ViewInstance::class, ['record' => Instance::comiteDirecteur()->getRouteKey()])->assertDontSee('Commissions et budget');
});

it('fait proposer le Jewrine du suivi par le président, valider par le Jewrine national, ou désigner par le super administrateur', function () {
    Notification::fake();
    $presidentCompte = $this->president->user;
    $sansCompte = Membre::factory()->valide()->create(['cellule_id' => $this->kaolack->id]);

    expect(fn () => $this->service->proposer($this->cdgmt, $sansCompte, $presidentCompte))->toThrow(DomainException::class, 'son compte')
        ->and(fn () => $this->service->proposer($this->comiteZiar, $this->moussa, $presidentCompte))->toThrow(DomainException::class, 'comité de suivi actif')
        ->and(fn () => $this->service->proposer($this->cdgmt, $this->moussa, $this->chef->user))->toThrow(DomainException::class, 'président');

    $proposition = $this->service->proposer($this->cdgmt, $this->moussa, $presidentCompte);
    Notification::assertSentTo($this->national, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->national)['titre'], 'Suivi des dépenses : proposition à valider'));
    expect(fn () => $this->service->proposer($this->cdgmt, $this->chef, $presidentCompte))->toThrow(DomainException::class, 'attend déjà')
        ->and($this->service->peutDecider($presidentCompte, $proposition))->toBeFalse();

    // Refusée avec un motif, puis une nouvelle proposition validée depuis l'espace.
    expect(fn () => $this->service->refuser($proposition, ' ', $this->national))->toThrow(DomainException::class, 'motif');
    $this->service->refuser($proposition, 'Déjà chef de commission ailleurs', $this->national);
    Notification::assertSentTo($presidentCompte, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($presidentCompte)['titre'], 'Suivi des dépenses : proposition refusée'));

    $this->actingAs($presidentCompte);
    Livewire::test(ViewInstance::class, ['record' => $this->cdgmt->getRouteKey()])
        ->callAction(TestAction::make('proposerSuivi'), data: ['membre_id' => $this->moussa->id])
        ->assertHasNoActionErrors();
    $this->actingAs($this->national);
    Livewire::test(SuiviDepensesComite::class, ['record' => $this->cdgmt])->assertSee('à valider par le Jewrine national');
    Livewire::test(ViewInstance::class, ['record' => $this->cdgmt->getRouteKey()])
        ->callAction(TestAction::make('validerSuivi'))
        ->assertHasNoActionErrors();

    $moussa = $this->moussa->user->fresh();
    expect($this->cdgmt->jewrineSuiviDepenses()?->id)->toBe($moussa->id)
        ->and($moussa->hasRole('jewrine_suivi_depenses'))->toBeTrue()
        ->and(Nomination::query()->where('instance_id', $this->cdgmt->id)->value('designe_par'))->toBe($this->national->id)
        ->and(PropositionSuiviDepenses::query()->where('statut', PropositionSuiviDepenses::VALIDEE)->count())->toBe(1);
    Notification::assertSentTo($moussa, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($moussa)['titre'], 'Jewrine du suivi des dépenses'));

    // Il retrouve son comité dans le panneau.
    $this->actingAs($moussa);
    expect(InstanceResource::canViewAny())->toBeTrue()
        ->and(InstanceResource::canView($this->cdgmt))->toBeTrue()
        ->and(InstanceResource::canView($this->comiteZiar))->toBeFalse();

    // Le super administrateur désigne directement : la désignation remplace la précédente.
    expect(fn () => $this->service->designer($this->cdgmt, $this->chef, $this->national))->toThrow(DomainException::class, 'super administrateur')
        ->and(fn () => $this->service->designer($this->comiteZiar, $this->chef, $this->super))->toThrow(DomainException::class, 'Activez');
    $this->service->designer($this->cdgmt, $this->chef, $this->super);
    expect($this->cdgmt->jewrineSuiviDepenses()?->id)->toBe($this->chef->user_id)
        ->and($moussa->fresh()->hasRole('jewrine_suivi_depenses'))->toBeFalse();

    // Le poste ne se donne pas depuis l'écran général des nominations.
    $this->actingAs($this->national);
    expect((fn () => self::postesNommables())->call(new NominationResource))->not->toContain('Jewrine du comité de suivi des dépenses');
});

it('fait arbitrer le budget par le Jewrine du suivi dans l\'enveloppe, avec chaque révision tracée et le chef prévenu', function () {
    Notification::fake();
    $this->service->designer($this->cdgmt, $this->moussa, $this->super);
    $arbitre = $this->moussa->user->fresh();

    expect($this->service->peutArbitrer($arbitre, $this->cdgmt))->toBeTrue()
        ->and($this->service->peutArbitrer($this->national, $this->cdgmt))->toBeFalse()
        ->and($this->service->peutArbitrer($this->president->user, $this->cdgmt))->toBeFalse()
        ->and($this->service->peutArbitrer($this->national, $this->comiteZiar))->toBeTrue()
        ->and($this->service->peutArbitrer($arbitre, $this->comiteZiar))->toBeFalse();

    $this->actingAs($arbitre);
    Livewire::test(CommissionsRelationManager::class, ['ownerRecord' => $this->cdgmt, 'pageClass' => ViewInstance::class])
        ->callAction(TestAction::make('arbitrer')->table($this->cuisine), data: ['budget' => 600000, 'motif' => 'Repas des trois jours'])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('arbitrer')->table($this->transport), data: ['budget' => 500000, 'motif' => 'Cars'])
        ->assertNotified('L\'enveloppe de 1 000 000 FCFA est dépassée : il reste 400 000 FCFA à répartir.')
        ->callAction(TestAction::make('repartir')->table(), data: ['depuis' => $this->cuisine->id, 'vers' => $this->transport->id, 'montant' => 100000, 'motif' => 'Un car de plus'])
        ->assertHasNoActionErrors();

    expect($this->cuisine->fresh()->budget())->toBe(500000)
        ->and($this->transport->fresh()->budget())->toBe(100000)
        ->and(RevisionBudget::query()->where('commission_comite_id', $this->cuisine->id)->count())->toBe(2)
        ->and(RevisionBudget::query()->latest('id')->value('motif'))->toBe('Depuis la commission Cuisine : Un car de plus')
        ->and(fn () => $this->service->arbitrer($this->cuisine, 1, ' ', $arbitre))->toThrow(DomainException::class, 'motif')
        ->and(fn () => $this->service->arbitrer($this->cuisine, 1, 'x', $this->national))->toThrow(DomainException::class, 'Jewrine du suivi');
    Notification::assertSentTo($this->chef->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->chef->user)['message'] === 'Votre budget est arrêté à 600 000 FCFA : Repas des trois jours.');

    // Le chef voit son budget arbitré et la dernière révision, sur son téléphone.
    $this->actingAs($this->chef->user)->get(route('comites.index'))->assertSee('Budget arbitré : 500 000 FCFA')->assertSee('Vers la commission Transport : Un car de plus');
});

it('montre l\'argent de l\'événement : promis, encaissé, en vérification, décaissé, disponible réel, enveloppe', function () {
    $collectes = app(CollecteService::class);
    $collectes->fixerParMembre($this->magal, $this->chef, 50000);
    $collectes->enregistrerEspeces($this->magal, $this->chef, 20000, $this->tresorier);
    $collectes->fixerParMembre($this->magal, $this->moussa, 30000);
    $collectes->declarer($this->magal, $this->moussa, 10000, 'T_MOUSSA00MAGAL1');
    $this->service->arbitrer($this->cuisine, 300000, 'Repas', $this->super);

    $ordres = app(OrdreDepenseService::class);
    $ordre = $ordres->demander(['objet' => 'Riz', 'libelle_depense_id' => LibelleDepense::query()->value('id'), 'montant' => 5000, 'beneficiaire' => 'Boutique',
        'rattachement' => 'programme', 'commission_comite_id' => $this->cuisine->id], null, $this->chef->user);
    $ordres->signer($ordre, $this->national);
    $ordres->executer($ordre->fresh(), app(TresorerieService::class)->caisse(TypeCaisse::Especes), UploadedFile::fake()->image('r.jpg'), $this->tresorier);

    expect($this->service->indicateurs($this->cdgmt))->toBe([
        'promis' => 80000, 'encaisse' => 20000, 'verification' => 10000, 'decaisse' => 5000, 'disponible' => 15000,
        'enveloppe' => 1000000, 'reparti' => 300000, 'a_repartir' => 700000, 'taches' => 0, 'taches_faites' => 0,
    ])->and($this->cuisine->fresh()->depense())->toBe(5000);

    $this->actingAs($this->national);
    Livewire::test(ViewInstance::class, ['record' => $this->cdgmt->getRouteKey()])->assertOk();
    Livewire::test(IndicateursComite::class, ['record' => $this->cdgmt])->assertSee('Disponible réel')->assertSee('15 000 FCFA')->assertSee('reste 700 000 FCFA');
    expect(Poste::query()->where('code', Poste::JEWRINE_SUIVI_DEPENSES)->value('perimetre'))->toBe('comite');
});

it('ouvre chaque onglet de l\'espace : réunions, collecte, argent, tâches, documents', function () {
    app(CollecteService::class)->fixerParMembre($this->magal, $this->chef, 50000);
    $ordre = app(OrdreDepenseService::class)->demander(['objet' => 'Riz', 'libelle_depense_id' => LibelleDepense::query()->value('id'), 'montant' => 5000, 'beneficiaire' => 'Boutique',
        'rattachement' => 'programme', 'commission_comite_id' => $this->cuisine->id], UploadedFile::fake()->create('devis.pdf', 10, 'application/pdf'), $this->chef->user);
    Reunion::query()->create(['instance_id' => $this->cdgmt->id, 'titre' => 'Réunion de lancement', 'debut' => now()->addDay(), 'lieu' => 'Siège', 'secretaire_id' => $this->chef->id]);
    ActionComite::query()->create(['instance_id' => $this->cdgmt->id, 'commission_id' => $this->cuisine->id, 'responsable_id' => $this->chef->id, 'description' => 'Commander 40 bols', 'echeance' => now()->subDay()]);
    $page = ['ownerRecord' => $this->cdgmt, 'pageClass' => ViewInstance::class];

    $this->actingAs($this->national);
    Livewire::test(ReunionsRelationManager::class, $page)->assertSee('Réunion de lancement')->assertSee('À venir ou en rédaction');
    Livewire::test(EngagementsRelationManager::class, $page)->assertSee('Awa')->assertSee('50 000 FCFA');
    Livewire::test(OrdresDepenseRelationManager::class, $page)->assertSee($ordre->numero)->assertSee('Cuisine');
    Livewire::test(TachesRelationManager::class, $page)->assertSee('Commander 40 bols')->assertSee('En retard');
    Livewire::test(DocumentsRelationManager::class, $page)->assertSee($ordre->numero)->assertActionVisible(TestAction::make('devis')->table($ordre));

    // Un simple membre du comité ne voit ni la collecte ni l'argent.
    $this->actingAs($this->chef->user);
    expect(EngagementsRelationManager::canViewForRecord($this->cdgmt, ViewInstance::class))->toBeFalse()
        ->and(OrdresDepenseRelationManager::canViewForRecord($this->cdgmt, ViewInstance::class))->toBeFalse()
        ->and(TachesRelationManager::canViewForRecord(Instance::comiteDirecteur(), ViewInstance::class))->toBeFalse();
});
