<?php

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Enums\TypeCaisse;
use App\Filament\Resources\Instances\InstanceResource;
use App\Filament\Resources\Instances\Pages\ViewInstance;
use App\Filament\Resources\Instances\RelationManagers\BilansRelationManager;
use App\Filament\Resources\Instances\Widgets\AmeliorationsPrecedentes;
use App\Models\BilanComite;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\CommissionComite;
use App\Models\LibelleDepense;
use App\Models\Membre;
use App\Models\OrdreDepense;
use App\Models\Reunion;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\BilanComiteService;
use App\Services\BudgetComiteService;
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
function membreCloture(Cellule $cellule, string $prenom): Membre
{
    $compte = User::factory()->create(['name' => $prenom.' Sy']);
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->valide()->create(['cellule_id' => $cellule->id, 'prenom' => $prenom]);
}

/** Un Magal organisé par un comité dédié, avec son président. */
function magalAvecComite(string $titre, Membre $president, User $super): Collecte
{
    return app(ProgrammeService::class)->enregistrer([
        'titre' => $titre, 'type_programme_id' => typeProgramme('magal'), 'debut' => now()->addMonths(2)->format('Y-m-d 08:00'), 'lieu' => 'Touba',
        'mode' => ModeCollecte::Jayante->value, 'budget' => 1000000, 'organisation' => Collecte::COMITE, 'president_id' => $president->id,
        'ouverture' => now()->subDay()->toDateString(), 'statut' => StatutCollecte::Ouverte->value,
    ], null, $super);
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->kaolack = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->national = nommerPourLeTest(membreCloture($this->kaolack, 'Babacar')->user, 'jewrine_national');
    $this->tresorier = nommerPourLeTest(membreCloture($this->kaolack, 'Fatou')->user, 'jewrine_finance');
    $this->evaluateur = membreCloture($this->kaolack, 'Adama')->user;
    $this->evaluateur->assignRole('suivi_evaluation');
    $this->president = membreCloture($this->kaolack, 'Serigne');
    $this->chef = membreCloture($this->kaolack, 'Awa');
    app(ComiteService::class)->definirComiteDirecteur([$this->president->id], $this->super);

    $this->magal = magalAvecComite('Grand Magal 2026', $this->president, $this->super);
    $this->cdgmt = $this->magal->comite ?? throw new RuntimeException('Pas de comité.');
    $this->cuisine = CommissionComite::query()->create(['instance_id' => $this->cdgmt->id, 'nom' => 'Cuisine', 'chef_id' => $this->chef->id]);
    app(ComiteService::class)->integrerResponsables($this->cdgmt);
    $this->service = app(BilanComiteService::class);

    // Une avance payée à la cheffe, pas encore justifiée.
    $payeur = membreCloture($this->kaolack, 'Binta');
    app(CollecteService::class)->fixerParMembre($this->magal, $payeur, 100000);
    app(CollecteService::class)->enregistrerEspeces($this->magal, $payeur, 100000, $this->tresorier);
    $ordres = app(OrdreDepenseService::class);
    $this->avance = $ordres->demander(['objet' => 'Riz', 'libelle_depense_id' => LibelleDepense::query()->where('nom', 'Achats de vivres')->value('id'), 'montant' => 40000,
        'beneficiaire' => '', 'nature' => OrdreDepense::AVANCE, 'rattachement' => 'programme', 'commission_comite_id' => $this->cuisine->id], null, $this->chef->user);
    $ordres->signer($this->avance, $this->national);
    $ordres->executer($this->avance->fresh(), app(TresorerieService::class)->caisse(TypeCaisse::Especes), UploadedFile::fake()->image('r.jpg'), $this->tresorier);
});

it('fait rendre les bilans, choisir la réunion de bilan, puis clôturer quand tout est rendu et justifié', function () {
    Notification::fake();
    expect(fn () => $this->service->cloturer($this->cdgmt, $this->president->user))
        ->toThrow(DomainException::class, 'Pas encore : bilan de la commission Cuisine ; bilan général du président ; évaluation de Suivi et Évaluation ; 1 avance(s) à justifier ou à rendre.');

    // La cheffe rend le bilan de sa commission depuis son téléphone, puis le complète.
    $this->actingAs($this->chef->user)->get(route('comites.index'))->assertSee('Bilan de la commission · à rendre à la fin de l\'événement');
    $this->actingAs($this->chef->user)->post(route('comites.bilans.store', $this->cuisine), ['appreciation' => 'partiels'])
        ->assertSessionHas('comite_erreur', 'Écrivez au moins les forces, les faiblesses ou les améliorations.');
    $this->actingAs($this->chef->user)->post(route('comites.bilans.store', $this->cuisine), [
        'appreciation' => 'partiels', 'forces' => 'Repas servis à l\'heure', 'ameliorations' => 'Acheter le riz un mois avant',
    ])->assertSessionHas('comite_succes');
    $this->actingAs($this->chef->user)->post(route('comites.bilans.store', $this->cuisine), [
        'appreciation' => 'atteints', 'forces' => 'Repas servis à l\'heure', 'faiblesses' => 'Trop peu de bols', 'ameliorations' => 'Acheter le riz un mois avant',
    ])->assertSessionHas('comite_succes');
    $bilan = BilanComite::query()->where('commission_comite_id', $this->cuisine->id)->sole();
    expect($bilan->appreciation)->toBe('atteints')
        ->and($bilan->chiffres)->toMatchArray(['budget' => 0, 'decaisse' => 40000, 'justifie' => 0, 'reste_a_justifier' => 40000])
        ->and(fn () => $this->service->rendreBilanCommission($this->cuisine, ['appreciation' => 'atteints', 'forces' => 'x'], membreCloture($this->kaolack, 'Ibou')->user))
        ->toThrow(DomainException::class, 'chef de la commission');
    Notification::assertSentTo($this->president->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->president->user)['titre'] === 'Bilan rendu : commission Cuisine');

    // Le président rend le bilan général et choisit la réunion de bilan ; Suivi et Évaluation rend son évaluation.
    $reunion = Reunion::query()->create(['instance_id' => $this->cdgmt->id, 'titre' => 'Réunion de bilan', 'debut' => now()->addDay(), 'lieu' => 'Siège', 'secretaire_id' => $this->chef->id]);
    $onglet = ['ownerRecord' => $this->cdgmt, 'pageClass' => ViewInstance::class];
    $this->actingAs($this->president->user);
    Livewire::test(BilansRelationManager::class, $onglet)
        ->assertSee('✘ Bilan général du président')
        ->assertActionHidden(TestAction::make('evaluer')->table())
        ->callAction(TestAction::make('bilanGeneral')->table(), data: ['appreciation' => 'atteints', 'forces' => 'Bonne organisation', 'ameliorations' => 'Commencer la collecte plus tôt'])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('reunionBilan')->table(), data: ['reunion_id' => $reunion->id])
        ->assertHasNoActionErrors();
    $this->actingAs($this->evaluateur);
    expect(InstanceResource::canView($this->cdgmt))->toBeTrue();
    Livewire::test(BilansRelationManager::class, $onglet)
        ->callAction(TestAction::make('evaluer')->table(), data: ['appreciation' => 'partiels', 'faiblesses' => 'Trop de dépenses de dernière minute'])
        ->assertHasNoActionErrors();
    expect($this->cdgmt->fresh()->reunion_bilan_id)->toBe($reunion->id)
        ->and($this->cdgmt->bilans()->count())->toBe(3);

    // Reste l'avance : la cheffe justifie, la Finance enregistre le reste ; alors seulement le comité se clôture.
    expect(fn () => $this->service->cloturer($this->cdgmt, $this->president->user))->toThrow(DomainException::class, '1 avance(s) à justifier ou à rendre');
    app(OrdreDepenseService::class)->justifier($this->avance->fresh(), 'Riz', 30000, UploadedFile::fake()->image('recu.jpg'), $this->chef->user);
    app(OrdreDepenseService::class)->enregistrerRetour($this->avance->fresh(), 10000, app(TresorerieService::class)->caisse(TypeCaisse::Especes), $this->tresorier);

    $this->actingAs($this->president->user);
    Livewire::test(BilansRelationManager::class, $onglet)
        ->callAction(TestAction::make('cloturerComite')->table())
        ->assertHasNoActionErrors()
        ->assertNotified('Clôturez maintenant l\'événement « Grand Magal 2026 »');
    Notification::assertSentTo($this->president->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->president->user)['titre'] === 'Clôturer l\'événement : Grand Magal 2026'
        && str_ends_with((string) $avis->toArray($this->president->user)['lien'], '/admin/collectes/'.$this->magal->id));
    expect($this->service->evenementAClore($this->cdgmt->fresh()))->not->toBeNull();
    $this->magal->update(['statut' => StatutCollecte::Cloturee]);
    expect($this->service->evenementAClore($this->cdgmt->fresh()))->toBeNull();
    $clos = $this->cdgmt->fresh();
    expect($clos->estClos())->toBeTrue()
        ->and($clos->cloture_par)->toBe($this->president->user_id);
    Notification::assertSentTo($this->chef->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->chef->user)['titre'], 'Comité clos'));

    // En archive : plus rien ne se modifie.
    expect(InstanceResource::canEdit($clos))->toBeFalse()
        ->and(app(SuiviDepensesService::class)->peutArbitrer($this->super, $clos))->toBeFalse()
        ->and(fn () => app(BudgetComiteService::class)->proposerBesoin($this->cuisine->fresh(), ['designation' => 'Riz', 'quantite' => 1, 'prix_unitaire' => 10], $this->chef->user))->toThrow(DomainException::class, 'clos')
        ->and(fn () => $this->service->rendreBilanGeneral($clos, ['appreciation' => 'atteints', 'forces' => 'x'], $this->president->user))->toThrow(DomainException::class, 'clos')
        ->and(fn () => app(OrdreDepenseService::class)->demander(['objet' => 'x', 'libelle_depense_id' => LibelleDepense::query()->value('id'), 'montant' => 1, 'beneficiaire' => 'x',
            'rattachement' => 'programme', 'commission_comite_id' => $this->cuisine->id], null, $this->super))->toThrow(DomainException::class, 'clos');
    Livewire::test(BilansRelationManager::class, ['ownerRecord' => $clos, 'pageClass' => ViewInstance::class])
        ->assertSee('archive en lecture seule')
        ->assertActionHidden(TestAction::make('bilanGeneral')->table())
        ->assertActionHidden(TestAction::make('cloturerComite')->table());

    // Le bilan en PDF reprend les bilans et les ordres payés.
    $this->get(route('comites.bilan', $clos))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('montre, à la mise en place du comité suivant, les améliorations notées la dernière fois', function () {
    foreach ([[null, BilanComite::GENERAL, 'Commencer la collecte plus tôt'], [$this->cuisine->id, BilanComite::COMMISSION, 'Acheter le riz un mois avant']] as [$commission, $type, $texte]) {
        BilanComite::query()->create(['instance_id' => $this->cdgmt->id, 'commission_comite_id' => $commission, 'type' => $type, 'appreciation' => 'atteints', 'ameliorations' => $texte, 'auteur_id' => $this->super->id]);
    }
    $this->cdgmt->update(['statut' => 'close', 'cloture_le' => now()]);

    $suivant = magalAvecComite('Grand Magal 2027', $this->president, $this->super)->comite ?? throw new RuntimeException('Pas de comité.');
    $ziar = app(ProgrammeService::class)->enregistrer([
        'titre' => 'Grand Ziar', 'type_programme_id' => typeProgramme('ziar'), 'debut' => now()->addMonth()->format('Y-m-d 08:00'), 'lieu' => 'Médina Baye',
        'mode' => ModeCollecte::Jayante->value, 'organisation' => Collecte::COMITE, 'ouverture' => now()->toDateString(), 'statut' => StatutCollecte::Ouverte->value,
    ], null, $this->super)->comite ?? throw new RuntimeException('Pas de comité.');

    expect($this->service->ameliorationsPrecedentes($suivant))->toHaveCount(2)
        ->and($this->service->ameliorationsPrecedentes($ziar))->toBe([])
        ->and($this->service->ameliorationsPrecedentes($this->cdgmt->fresh()))->toBe([]);

    $this->actingAs($this->president->user);
    Livewire::test(AmeliorationsPrecedentes::class, ['record' => $suivant])
        ->assertSee('Les améliorations notées la dernière fois')
        ->assertSee('Commencer la collecte plus tôt')
        ->assertSee('Grand Magal 2026 · Commission Cuisine');
    Livewire::test(ViewInstance::class, ['record' => $suivant->getRouteKey()])->assertSee('Bilan');
});
