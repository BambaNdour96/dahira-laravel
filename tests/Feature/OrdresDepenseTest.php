<?php

use App\Enums\StatutProjet;
use App\Enums\TypeCaisse;
use App\Filament\Resources\LibellesDepense\LibelleDepenseResource;
use App\Filament\Resources\OrdresDepense\OrdreDepenseResource;
use App\Filament\Resources\OrdresDepense\Pages\ListOrdresDepense;
use App\Models\Cellule;
use App\Models\Commission;
use App\Models\CommissionComite;
use App\Models\Instance;
use App\Models\LibelleDepense;
use App\Models\Membre;
use App\Models\MouvementCaisse;
use App\Models\OrdreDepense;
use App\Models\Projet;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CotisationService;
use App\Services\OrdreDepenseService;
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
function membreOrdre(Cellule $cellule): Membre
{
    $compte = User::factory()->create();
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->valide()->create(['cellule_id' => $cellule->id, 'valide_le' => now()->startOfMonth()->subMonths(6)]);
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class, CommissionsSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->jewrine = User::factory()->create();
    $this->kaolack = celluleDirigeePar($this->jewrine, ['nom' => 'Kaolack']);
    $this->dakar = Cellule::factory()->create(['nom' => 'Dakar']);
    $this->national = nommerPourLeTest(membreOrdre($this->kaolack)->user, 'jewrine_national');
    $this->tresorier = nommerPourLeTest(membreOrdre($this->kaolack)->user, 'jewrine_finance');
    $this->libelle = LibelleDepense::query()->where('nom', 'Achats de vivres')->firstOrFail();
    $this->service = app(OrdreDepenseService::class);

    // De quoi payer : 5 mois de cotisations en espèces dans la caisse générale.
    $payeur = membreOrdre($this->kaolack);
    app(CotisationService::class)->enregistrerEspeces($payeur, 3, $this->tresorier);
    app(CotisationService::class)->enregistrerEspeces($payeur->fresh() ?? $payeur, 2, $this->tresorier);
});

function ordre(array $valeurs = []): array
{
    return [
        'objet' => 'Achat de 40 bols', 'libelle_depense_id' => LibelleDepense::query()->value('id'), 'montant' => 3000,
        'beneficiaire' => 'Quincaillerie Médina', 'rattachement' => 'fonctionnement', ...$valeurs,
    ];
}

it('fait demander par le chef de commission, signer par le Jewrine national, puis payer par la Finance', function () {
    Notification::fake();
    $chef = membreOrdre($this->kaolack);
    $cdgmt = Instance::query()->create(['type' => Instance::CDGMT, 'nom' => 'CDGMT 2026', 'code' => 'CDGMT', 'annee' => 2026]);
    $cuisine = CommissionComite::query()->create(['instance_id' => $cdgmt->id, 'nom' => 'Cuisine', 'chef_id' => $chef->id]);

    $this->actingAs($chef->user)->get(route('comites.index'))->assertSee('Demander un ordre de dépense');
    $this->actingAs($chef->user)->post(route('comites.ordres.store', $cuisine), [
        'objet' => 'Riz pour 300 personnes', 'libelle_depense_id' => $this->libelle->id, 'montant' => 4000, 'beneficiaire' => 'Boutique Ndiaye',
        'devis' => UploadedFile::fake()->create('devis.pdf', 20, 'application/pdf'),
    ])->assertSessionHas('comite_succes');

    $ordre = OrdreDepense::query()->firstOrFail();
    expect($ordre->numero)->toBe('OD-'.now()->format('Y').'-0001')
        ->and($ordre->imputation())->toContain('commission Cuisine')
        ->and($ordre->devis_path)->not->toBeNull();
    Notification::assertSentTo($this->national, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->national)['titre'], 'Ordre de dépense à signer'));

    $this->actingAs($this->tresorier);
    Livewire::test(ListOrdresDepense::class)->assertActionHidden(TestAction::make('signer')->table($ordre))->assertActionHidden(TestAction::make('executer')->table($ordre));

    $this->actingAs($this->national);
    Livewire::test(ListOrdresDepense::class)->callAction(TestAction::make('signer')->table($ordre))->assertHasNoActionErrors();
    expect($ordre->fresh()->statut)->toBe(OrdreDepense::SIGNE);

    // La caisse Wave est vide : refusé ; la caisse générale paie.
    $wave = app(TresorerieService::class)->caisse(TypeCaisse::Wave);
    $generale = app(TresorerieService::class)->caisse(TypeCaisse::Especes);
    expect(fn () => $this->service->executer($ordre->fresh(), $wave, UploadedFile::fake()->image('recu.jpg'), $this->tresorier))->toThrow(DomainException::class, 'Solde insuffisant')
        ->and(fn () => $this->service->executer($ordre->fresh(), $generale, UploadedFile::fake()->image('recu.jpg'), $this->national))->toThrow(DomainException::class, 'La Finance exécute');

    $this->service->executer($ordre->fresh(), $generale, UploadedFile::fake()->image('recu.jpg'), $this->tresorier);
    expect($ordre->fresh()->statut)->toBe(OrdreDepense::EXECUTE)
        ->and($generale->solde())->toBe(1000)
        ->and(MouvementCaisse::query()->where('ordre_depense_id', $ordre->id)->value('sens'))->toBe(MouvementCaisse::SORTIE);
    Notification::assertSentTo($chef->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($chef->user)['titre'], 'Ordre exécuté'));

    $this->actingAs($chef->user)->get(route('ordres-depense.piece', [$ordre, 'preuve']))->assertOk();
    $this->actingAs(membreOrdre($this->dakar)->user)->get(route('ordres-depense.piece', [$ordre, 'preuve']))->assertForbidden();
});

it('ne laisse personne signer son propre ordre, ni le signataire payer, et garde la caisse positive', function () {
    $ordre = $this->service->demander(ordre(['montant' => 2000]), null, $this->super);

    expect($this->service->peutSigner($this->super, $ordre))->toBeFalse()
        ->and($this->service->peutSigner($this->national, $ordre))->toBeTrue();

    $this->service->signer($ordre, $this->national);
    $generale = app(TresorerieService::class)->caisse(TypeCaisse::Especes);
    // Le signataire ne paie jamais ; le demandeur peut payer son ordre une fois signé.
    expect($this->service->peutExecuter($this->national, $ordre->fresh()))->toBeFalse()
        ->and($this->service->peutExecuter($this->super, $ordre->fresh()))->toBeTrue();

    $this->service->executer($ordre->fresh(), $generale, UploadedFile::fake()->image('recu.jpg'), $this->tresorier);
    expect($generale->solde())->toBe(3000);

    $gros = $this->service->demander(ordre(['montant' => 50000]), null, $this->tresorier);
    $this->service->signer($gros, $this->national);
    expect($this->service->peutExecuter($this->tresorier, $gros->fresh()))->toBeTrue()
        ->and(fn () => $this->service->executer($gros->fresh(), $generale, UploadedFile::fake()->image('r.jpg'), $this->tresorier))->toThrow(DomainException::class, 'Solde insuffisant')
        ->and($generale->solde())->toBe(3000);
});

it('limite chaque demandeur à son périmètre : cellule, commission permanente, projet', function () {
    expect($this->service->demander(ordre(['cellule_id' => $this->kaolack->id]), null, $this->jewrine->fresh())->imputation())->toBe('Fonctionnement · cellule Kaolack')
        ->and(fn () => $this->service->demander(ordre(['cellule_id' => $this->dakar->id]), null, $this->jewrine->fresh()))->toThrow(DomainException::class, 'votre périmètre');

    $culturelle = Commission::query()->where('code', Commission::CULTURELLE)->firstOrFail();
    $jewrineCulturel = nommerPourLeTest(membreOrdre($this->dakar)->user, 'jewrine_commission', null, $culturelle);
    expect($this->service->demander(ordre(['commission_id' => $culturelle->id]), null, $jewrineCulturel)->imputation())->toBe('Fonctionnement · commission Culturelle');

    $responsable = membreOrdre($this->dakar);
    $projet = Projet::query()->create(['titre' => 'Daara de Médina', 'objectif' => 'Construire', 'responsable_id' => $responsable->id, 'date_debut' => now(), 'date_fin' => now()->addYear(), 'statut' => StatutProjet::cases()[0]]);
    $pourProjet = $this->service->demander(ordre(['rattachement' => 'projet', 'projet_id' => $projet->id, 'montant' => 1500]), null, $responsable->user);
    $this->service->signer($pourProjet, $this->national);
    $this->service->executer($pourProjet->fresh(), app(TresorerieService::class)->caisse(TypeCaisse::Especes), UploadedFile::fake()->image('r.jpg'), $this->tresorier);
    expect($projet->fresh()->depenses())->toBe(1500);

    $simple = membreOrdre($this->dakar);
    $this->actingAs($simple->user);
    expect($this->service->peutDemander($simple->user))->toBeFalse()
        ->and(OrdreDepenseResource::canViewAny())->toBeFalse()
        ->and(LibelleDepenseResource::canViewAny())->toBeFalse();
});

it('laisse refuser avec un motif, et le demandeur annuler tant que l\'ordre n\'est pas signé', function () {
    $refuse = $this->service->demander(ordre(), null, $this->tresorier);
    expect(fn () => $this->service->refuser($refuse, '', $this->national))->toThrow(DomainException::class, 'motif');
    $this->service->refuser($refuse, 'Pas prévu au budget', $this->national);
    expect($refuse->fresh()->statut)->toBe(OrdreDepense::REFUSE);

    $annule = $this->service->demander(ordre(), null, $this->tresorier);
    expect(fn () => $this->service->annuler($annule, $this->national))->toThrow(DomainException::class);
    $this->service->annuler($annule, $this->tresorier);
    expect($annule->fresh()->statut)->toBe(OrdreDepense::ANNULE);

    $this->actingAs($this->tresorier);
    expect(LibelleDepenseResource::canViewAny())->toBeTrue()
        ->and(OrdreDepenseResource::canViewAny())->toBeTrue();

    Livewire::test(ListOrdresDepense::class)
        ->callAction('demander', ['objet' => 'Frais de transfert Wave', 'libelle_depense_id' => $this->libelle->id, 'montant' => 500, 'beneficiaire' => 'Wave', 'rattachement' => 'fonctionnement'])
        ->assertHasNoActionErrors();
    expect(OrdreDepense::query()->where('objet', 'Frais de transfert Wave')->value('statut'))->toBe(OrdreDepense::DEMANDE);
});
