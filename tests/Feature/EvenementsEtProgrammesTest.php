<?php

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Enums\TypeCaisse;
use App\Filament\Resources\Collectes\Pages\CreateCollecte;
use App\Filament\Resources\Collectes\Pages\EditCollecte;
use App\Filament\Resources\Collectes\Pages\ViewCollecte;
use App\Filament\Resources\Collectes\Widgets\BilanDuProgramme;
use App\Filament\Resources\TypesProgramme\Pages\ManageTypesProgramme;
use App\Filament\Resources\TypesProgramme\TypeProgrammeResource;
use App\Models\ActionComite;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\Commission;
use App\Models\Instance;
use App\Models\LibelleDepense;
use App\Models\Membre;
use App\Models\ProgrammeCommission;
use App\Models\ReaffectationFonds;
use App\Models\TypeProgramme;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CollecteService;
use App\Services\CommissionService;
use App\Services\FinanceRapportService;
use App\Services\OrdreDepenseService;
use App\Services\ProgrammeService;
use App\Services\ReaffectationService;
use App\Services\TresorerieService;
use Database\Seeders\CommissionsSeeder;
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
function membreEvenement(Cellule $cellule, array $attributs = []): Membre
{
    $compte = User::factory()->create();
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->valide()->create(['cellule_id' => $cellule->id, ...$attributs]);
}

/**
 * Les champs d'un événement, à compléter.
 *
 * @return array<string, mixed>
 */
function evenement(array $valeurs = []): array
{
    return [
        'titre' => 'Prokhane 2026', 'type_programme_id' => typeProgramme('prokhane'), 'debut' => now()->addMonths(2)->format('Y-m-d 17:00'),
        'lieu' => 'Kaolack', 'organisation' => Collecte::COMMISSIONS, 'ouverture' => now()->subDay()->toDateString(), 'statut' => StatutCollecte::Ouverte->value,
        ...$valeurs,
    ];
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class, CommissionsSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->kaolack = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->national = nommerPourLeTest(membreEvenement($this->kaolack)->user, 'jewrine_national');
    $this->tresorier = nommerPourLeTest(membreEvenement($this->kaolack)->user, 'jewrine_finance');
    $this->awa = membreEvenement($this->kaolack, ['prenom' => 'Awa', 'nom' => 'Diop']);
    $this->culturelle = Commission::query()->where('code', Commission::CULTURELLE)->firstOrFail();
    $this->organisation = Commission::query()->where('nom', 'Organisation')->firstOrFail();
    $this->jewrineCulturel = nommerPourLeTest(membreEvenement($this->kaolack)->user, 'jewrine_commission', null, $this->culturelle);
    $this->service = app(ProgrammeService::class);
});

it('laisse le Jewrine national créer un événement ; le super administrateur choisit le Sass, le Jewrine national le fixe', function () {
    Notification::fake();
    $this->actingAs($this->national);

    Livewire::test(CreateCollecte::class)
        ->assertFormFieldIsDisabled('mode')
        ->fillForm(evenement(['avec_jour_j' => true, 'objectif' => 1500000, 'budget' => 600000]))
        ->call('create')
        ->assertHasNoFormErrors();

    $prokhane = Collecte::query()->where('titre', 'Prokhane 2026')->firstOrFail();
    expect($prokhane->mode)->toBeNull()
        ->and($prokhane->estOuverte())->toBeFalse()
        ->and($prokhane->evenement?->lieu)->toBe('Kaolack')
        ->and($prokhane->budget)->toBe(600000);
    Notification::assertSentTo($this->super, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->super)['titre'], 'Contribution à choisir'));

    // Le Jewrine national ne peut pas imposer le mode, même par le service.
    $this->service->enregistrer([...evenement(), 'mode' => ModeCollecte::Jayante->value], $prokhane, $this->national);
    expect($prokhane->fresh()->mode)->toBeNull();

    // Le super administrateur choisit le Sass ; le Jewrine national, prévenu, en fixe le montant.
    $this->actingAs($this->super);
    Livewire::test(EditCollecte::class, ['record' => $prokhane->getRouteKey()])
        ->fillForm(['mode' => ModeCollecte::Sass->value])
        ->call('save')
        ->assertHasNoFormErrors();
    Notification::assertSentTo($this->national, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->national)['titre'], 'Sass à fixer'));

    $this->actingAs($this->national);
    Livewire::test(EditCollecte::class, ['record' => $prokhane->getRouteKey()])
        ->fillForm(['montant' => 5000])
        ->call('save')
        ->assertHasNoFormErrors();

    $prokhane->refresh();
    expect($prokhane->estOuverte())->toBeTrue()
        ->and($prokhane->engagements()->count())->toBe(4)
        ->and($prokhane->engagements()->where('membre_id', $this->awa->id)->value('montant'))->toBe(5000);

    // Awa donne davantage depuis son téléphone ; le mode ne change plus.
    $this->actingAs($this->awa->user)->get(route('engagements.index'))->assertSee('Le Sass est de 5 000 FCFA');
    $this->actingAs($this->awa->user)->post(route('engagements.augmenter', $prokhane), ['montant' => 10000, 'collecte_id' => $prokhane->id])->assertRedirect(route('engagements.index'));
    $this->actingAs($this->awa->user)->post(route('engagements.augmenter', $prokhane), ['montant' => 2000, 'collecte_id' => $prokhane->id])->assertSessionHasErrors('augmenter');
    expect($prokhane->engagements()->where('membre_id', $this->awa->id)->value('montant'))->toBe(10000)
        ->and(fn () => $this->service->enregistrer([...evenement(), 'mode' => ModeCollecte::Jayante->value], $prokhane, $this->super))->toThrow(DomainException::class, 'ne change plus');
});

it('vérifie le formulaire : type en activité, date et lieu, organisation, parts dans le budget', function () {
    $type = TypeProgramme::query()->create(['nom' => 'Kazu Rajab', 'ordre' => 9]);
    expect($type->code)->toBe('kazu_rajab');
    $type->forceFill(['archive_le' => now()])->save();

    expect(fn () => $this->service->enregistrer(evenement(['type_programme_id' => $type->id]), null, $this->super))->toThrow(DomainException::class, 'en activité')
        ->and(fn () => $this->service->enregistrer(evenement(['lieu' => ' ']), null, $this->super))->toThrow(DomainException::class, 'le lieu')
        ->and(fn () => $this->service->enregistrer(evenement(['fin' => now()->toDateString()]), null, $this->super))->toThrow(DomainException::class, 'après son début')
        ->and(fn () => $this->service->enregistrer(evenement(['organisation' => null]), null, $this->super))->toThrow(DomainException::class, 'organiser')
        ->and(fn () => $this->service->enregistrer(evenement(['budget' => 100000, 'commissions' => [['commission_id' => $this->culturelle->id, 'budget' => 150000]]]), null, $this->super))
        ->toThrow(DomainException::class, 'dépassent le budget')
        ->and(fn () => $this->service->enregistrer(evenement(), null, $this->tresorier))->toThrow(DomainException::class, 'Jewrine national')
        ->and(fn () => $this->service->enregistrer(evenement(['mode' => ModeCollecte::Sass->value, 'montant' => -5]), null, $this->super))->toThrow(DomainException::class, 'positif');
});

it('organise par commissions : leur part du budget, les tâches confiées puis réparties, leurs dépenses', function () {
    Notification::fake();
    $prokhane = $this->service->enregistrer(evenement([
        'mode' => ModeCollecte::Sass->value, 'montant' => 5000, 'objectif' => 20000, 'budget' => 600000,
        'commissions' => [['commission_id' => $this->culturelle->id, 'budget' => 350000], ['commission_id' => $this->organisation->id, 'budget' => null]],
    ]), null, $this->super);
    Notification::assertSentTo($this->jewrineCulturel, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->jewrineCulturel)['titre'], 'Votre commission organise'));

    // Décocher une commission la retire sans l'effacer.
    $this->service->enregistrer(evenement(['mode' => ModeCollecte::Sass->value, 'montant' => 5000, 'objectif' => 20000, 'budget' => 600000,
        'commissions' => [['commission_id' => $this->culturelle->id, 'budget' => 350000]]]), $prokhane, $this->super);
    expect(ProgrammeCommission::query()->where('commission_id', $this->organisation->id)->whereNotNull('retiree_le')->exists())->toBeTrue()
        ->and(fn () => $this->service->confierTache($prokhane, $this->organisation, 'Chaises', null, $this->national))->toThrow(DomainException::class, 'pas choisie');

    // Le Jewrine national confie une tâche ; le Jewrine de la commission la répartit depuis « Mes comités ».
    $this->actingAs($this->national);
    Livewire::test(ViewCollecte::class, ['record' => $prokhane->getRouteKey()])
        ->callAction('confierTache', ['commission_id' => $this->culturelle->id, 'description' => 'Programme des khassaides', 'echeance' => now()->addMonth()->toDateString()])
        ->assertHasNoActionErrors();
    $tache = ActionComite::query()->where('collecte_id', $prokhane->id)->firstOrFail();
    expect($tache->responsable_id)->toBeNull()
        ->and(fn () => $this->service->confierTache($prokhane, $this->culturelle, 'x', null, $this->tresorier))->toThrow(DomainException::class);

    $moussa = membreEvenement($this->kaolack, ['prenom' => 'Moussa']);
    app(CommissionService::class)->ajouter($this->culturelle, $moussa, $this->jewrineCulturel);
    $this->actingAs($this->jewrineCulturel)->get(route('comites.index'))
        ->assertSee('Événements que nous organisons')->assertSee('Prokhane 2026')->assertSee('Programme des khassaides')->assertSee('À répartir');
    expect(fn () => $this->service->repartirTache($tache, $this->awa, $this->jewrineCulturel))->toThrow(DomainException::class, 'membre de la commission')
        ->and(fn () => $this->service->repartirTache($tache, $moussa, $this->awa->user))->toThrow(DomainException::class, 'Jewrine de la commission');
    $this->actingAs($this->jewrineCulturel)->post(route('comites.taches.repartir', $tache), ['membre_id' => $moussa->id])->assertSessionHas('comite_succes');
    expect($tache->fresh()->responsable_id)->toBe($moussa->id);
    $this->actingAs($this->jewrineCulturel)->get(route('comites.index'))->assertSee('Confiée à Moussa');
    $this->actingAs($moussa->user)->get(route('comites.index'))->assertSee('Mes actions à mener')->assertSee('Programme des khassaides');

    // Le Jewrine de la commission demande une dépense pour l'événement ; elle compte dans sa part du budget.
    app(CollecteService::class)->enregistrerEspeces($prokhane, $this->awa, 5000, $this->tresorier);
    $ordres = app(OrdreDepenseService::class);
    $ordre = $ordres->demander(['objet' => 'Sonorisation', 'libelle_depense_id' => LibelleDepense::query()->value('id'), 'montant' => 3000, 'beneficiaire' => 'Studio Baye',
        'rattachement' => 'programme', 'collecte_id' => $prokhane->id, 'commission_id' => $this->culturelle->id], null, $this->jewrineCulturel);
    expect($ordre->imputation())->toBe('Prokhane 2026 · commission Culturelle')
        ->and(fn () => $ordres->demander(['objet' => 'x', 'libelle_depense_id' => LibelleDepense::query()->value('id'), 'montant' => 1, 'beneficiaire' => 'x',
            'rattachement' => 'programme', 'collecte_id' => $prokhane->id, 'commission_id' => $this->organisation->id], null, $this->super))->toThrow(DomainException::class, 'pas choisie');
    $ordres->signer($ordre, $this->national);
    $ordres->executer($ordre->fresh(), app(TresorerieService::class)->caisse(TypeCaisse::Especes), UploadedFile::fake()->image('r.jpg'), $this->tresorier);

    $bilan = $this->service->bilan($prokhane);
    expect($bilan['encaisse'])->toBe(5000)
        ->and($bilan['atteint'])->toBeFalse()
        ->and($bilan['taux'])->toBe(25)
        ->and($bilan['depense'])->toBe(3000)
        ->and($bilan['depasse'])->toBeFalse()
        ->and($bilan['solde'])->toBe(2000)
        ->and($bilan['commissions'])->toBe([['nom' => 'Culturelle', 'budget' => 350000, 'depense' => 3000]]);
    Livewire::test(BilanDuProgramme::class, ['record' => $prokhane])->assertSee('Commission Culturelle')->assertSee('Sass de 5 000 FCFA');
});

it('crée le comité dédié du Magal avec son président, prévenu', function () {
    Notification::fake();
    $president = membreEvenement($this->kaolack, ['prenom' => 'Serigne']);
    $magal = $this->service->enregistrer(evenement([
        'titre' => 'Grand Magal 2026', 'type_programme_id' => typeProgramme('magal'), 'organisation' => Collecte::COMITE, 'president_id' => $president->id,
        'mode' => ModeCollecte::Jayante->value, 'itineraire_transport' => true,
    ]), null, $this->super);

    expect($magal->comite?->type)->toBe(Instance::CDGMT)
        ->and($magal->comite?->president_id)->toBe($president->id)
        ->and($magal->comite?->membres()->whereKey($president->id)->exists())->toBeTrue()
        ->and($magal->itineraire_transport)->toBeTrue();
    Notification::assertSentTo($president->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($president->user)['titre'], 'Président du comité'));

    $autre = membreEvenement($this->kaolack);
    $this->service->enregistrer(evenement(['titre' => 'Grand Magal 2026', 'type_programme_id' => typeProgramme('magal'), 'organisation' => Collecte::COMITE, 'president_id' => $autre->id]), $magal, $this->super);
    expect(Instance::query()->where('collecte_id', $magal->id)->count())->toBe(1)
        ->and($magal->comite()->value('president_id'))->toBe($autre->id);

    // Un Ziar organisé par un comité « autre ».
    $ziar = $this->service->enregistrer(evenement(['titre' => 'Grand Ziar', 'type_programme_id' => typeProgramme('ziar'), 'organisation' => Collecte::COMITE]), null, $this->super);
    expect($ziar->comite?->type)->toBe('autre');
});

it('clôture : le solde rejoint la caisse générale par une réaffectation que la Finance enregistre', function () {
    Notification::fake();
    $gamou = $this->service->enregistrer(evenement(['titre' => 'Gamou 2026', 'type_programme_id' => typeProgramme('gamou'), 'mode' => ModeCollecte::Jayante->value]), null, $this->super);
    app(CollecteService::class)->fixerParMembre($gamou, $this->awa, 8000);
    app(CollecteService::class)->enregistrerEspeces($gamou, $this->awa, 8000, $this->tresorier);

    expect(fn () => $this->service->cloturer($gamou, $this->tresorier))->toThrow(DomainException::class)
        ->and($this->service->peutVerserLeSolde($this->national, $gamou))->toBeFalse();

    $this->actingAs($this->national);
    Livewire::test(ViewCollecte::class, ['record' => $gamou->getRouteKey()])->callAction('cloturer')->assertHasNoActionErrors();
    $reaffectation = ReaffectationFonds::query()->where('origine_collecte_id', $gamou->id)->firstOrFail();
    expect($gamou->fresh()->statut)->toBe(StatutCollecte::Cloturee)
        ->and($reaffectation->destination)->toBe('fonctionnement')
        ->and($reaffectation->montant)->toBe(8000)
        ->and($this->service->peutVerserLeSolde($this->national, $gamou->fresh()))->toBeFalse()
        ->and($this->service->bilan($gamou)['solde_reaffecte'])->toContain('Décidée');

    app(ReaffectationService::class)->enregistrer($reaffectation, $this->tresorier);
    expect(app(FinanceRapportService::class)->soldeProgramme($gamou))->toBe(0)
        ->and(app(FinanceRapportService::class)->soldeFonctionnement())->toBe(8000);

    // Clos par le super administrateur sans solde : rien à verser.
    $vide = $this->service->enregistrer(evenement(['titre' => 'Laylatoul Qadr', 'type_programme_id' => typeProgramme('laylatoul_qadr')]), null, $this->super);
    expect($this->service->cloturer($vide, $this->super))->toBeNull();
});

it('laisse le super administrateur compléter et archiver les types d\'événements', function () {
    $this->actingAs($this->super);
    Livewire::test(ManageTypesProgramme::class)
        ->callAction('create', ['nom' => 'Kazu Rajab', 'ordre' => 6])
        ->assertHasNoActionErrors();
    $type = TypeProgramme::query()->where('nom', 'Kazu Rajab')->firstOrFail();

    Livewire::test(ManageTypesProgramme::class)->callAction(TestAction::make('archiver')->table($type))->assertHasNoActionErrors();
    expect($type->fresh()->estArchive())->toBeTrue();

    $this->actingAs($this->national);
    expect(TypeProgrammeResource::canViewAny())->toBeFalse();
    $this->actingAs($this->national)->get(route('collectes.fiche'))->assertOk();
});
