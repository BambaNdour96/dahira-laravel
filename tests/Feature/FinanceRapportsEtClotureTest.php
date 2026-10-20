<?php

use App\Enums\ModeCollecte;
use App\Enums\ProgrammeCollecte;
use App\Enums\StatutCollecte;
use App\Enums\StatutProjet;
use App\Enums\TypeCaisse;
use App\Filament\Pages\RapportsFinanciers;
use App\Filament\Resources\Exercices\ExerciceResource;
use App\Filament\Resources\Exercices\Pages\ManageExercices;
use App\Filament\Resources\Reaffectations\Pages\ListReaffectations;
use App\Filament\Resources\Reaffectations\ReaffectationResource;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\Exercice;
use App\Models\JournalExport;
use App\Models\LibelleDepense;
use App\Models\Membre;
use App\Models\MouvementCaisse;
use App\Models\Projet;
use App\Models\ReaffectationFonds;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CollecteService;
use App\Services\CorrectionPaiementService;
use App\Services\CotisationService;
use App\Services\ExerciceService;
use App\Services\FinanceRapportService;
use App\Services\OrdreDepenseService;
use App\Services\ReaffectationService;
use App\Services\TresorerieService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Un membre validé avec son compte. */
function membreBilan(Cellule $cellule, array $attributs = []): Membre
{
    $compte = User::factory()->create();
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->valide()->create(['cellule_id' => $cellule->id, 'valide_le' => now()->startOfMonth()->subMonths(6), ...$attributs]);
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->kaolack = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->dakar = Cellule::factory()->create(['nom' => 'Dakar']);
    $this->national = nommerPourLeTest(membreBilan($this->kaolack)->user, 'jewrine_national');
    $this->tresorier = nommerPourLeTest(membreBilan($this->kaolack)->user, 'jewrine_finance');
    $this->awa = membreBilan($this->kaolack, ['prenom' => 'Awa', 'nom' => 'Diop']);
    $this->modou = membreBilan($this->dakar, ['prenom' => 'Modou', 'nom' => 'Fall']);
    $this->magal = Collecte::query()->create(['titre' => 'Magal 2026', 'programme' => ProgrammeCollecte::Magal, 'mode' => ModeCollecte::EngagementLibre, 'ouverture' => now()->subMonth(), 'statut' => StatutCollecte::Ouverte]);
    $this->projet = Projet::query()->create(['titre' => 'Daara de Médina', 'objectif' => 'Construire', 'responsable_id' => $this->awa->id, 'date_debut' => now(), 'date_fin' => now()->addYear(), 'statut' => StatutProjet::cases()[0]]);
    $this->rapports = app(FinanceRapportService::class);
    $this->generale = app(TresorerieService::class)->caisse(TypeCaisse::Especes);
});

/** Paie un ordre de dépense depuis la caisse générale. */
function payerOrdre(array $donnees, User $tresorier, User $national): void
{
    $service = app(OrdreDepenseService::class);
    $ordre = $service->demander([
        'objet' => 'Achat', 'libelle_depense_id' => LibelleDepense::query()->where('nom', 'Achats de vivres')->value('id'), 'beneficiaire' => 'Boutique', ...$donnees,
    ], null, $tresorier);
    $service->signer($ordre, $national);
    $service->executer($ordre->fresh(), app(TresorerieService::class)->caisse(TypeCaisse::Especes), UploadedFile::fake()->image('recu.jpg'), $tresorier);
}

it('tire les recettes et les dépenses du journal des caisses, corrections comprises', function () {
    $cotisations = app(CotisationService::class);
    $cotisationAwa = $cotisations->enregistrerEspeces($this->awa, 3, $this->tresorier);
    $cotisations->enregistrerEspeces($this->modou, 2, $this->tresorier);
    app(CollecteService::class)->fixerParMembre($this->magal, $this->modou, 10000);
    app(CollecteService::class)->enregistrerEspeces($this->magal, $this->modou, 4000, $this->tresorier);
    payerOrdre(['montant' => 1500, 'rattachement' => 'programme', 'collecte_id' => $this->magal->id], $this->tresorier, $this->national);

    // Une cotisation d'Awa réaffectée vers son Jàyante : l'argent change de source sans changer de caisse.
    app(CollecteService::class)->fixerParMembre($this->magal, $this->awa, 5000);
    app(CorrectionPaiementService::class)->reaffecter($cotisationAwa, ['objet' => 'collecte', 'collecte_id' => $this->magal->id], 'Voulait payer le Magal', $this->tresorier);
    $soldeAvant = $this->generale->solde();

    $rapport = $this->rapports->rapport(now()->startOfMonth(), now()->endOfMonth());
    expect($rapport['recettes'])->toBe(9000)
        ->and($rapport['depenses'])->toBe(1500)
        ->and($rapport['recettes_par_source'])->toBe(['Cotisations' => 2000, 'Jàyante et Sass' => 7000])
        ->and($rapport['recettes_par_cellule'])->toBe(['Dakar' => 6000, 'Kaolack' => 3000])
        ->and($rapport['recettes_par_programme'])->toBe(['Magal 2026' => 7000])
        ->and($rapport['depenses_par_libelle'])->toBe(['Achats de vivres' => 1500])
        ->and($rapport['caisses'][$this->generale->nom])->toBe(7500)
        ->and($this->generale->solde())->toBe($soldeAvant)
        ->and($this->rapports->soldeProgramme($this->magal))->toBe(5500)
        ->and($this->rapports->detailParMembre(now()->startOfMonth(), now()->endOfMonth())[$this->awa->nom_complet])->toBe(['Cotisations' => 0, 'Jàyante et Sass' => 3000]);

    // Une contre-passation compte en négatif, le mois où elle a lieu.
    $this->travel(1)->month();
    app(CorrectionPaiementService::class)->contrePasser($this->modou->paiements()->where('objet', 'cotisation')->firstOrFail(), 'Saisi deux fois', $this->tresorier);
    expect($this->rapports->rapport(now()->startOfMonth(), now()->endOfMonth())['recettes'])->toBe(-2000)
        ->and($this->rapports->rapport(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth())['recettes'])->toBe(9000);
});

it('fait décider la réaffectation par le Jewrine national et l\'enregistrer par la Finance, si le fonds a de quoi', function () {
    Notification::fake();
    app(CollecteService::class)->fixerParMembre($this->magal, $this->modou, 10000);
    app(CollecteService::class)->enregistrerEspeces($this->magal, $this->modou, 6000, $this->tresorier);
    $service = app(ReaffectationService::class);

    expect(fn () => $service->decider(['origine' => 'programme', 'origine_collecte_id' => $this->magal->id, 'destination' => 'projet', 'destination_projet_id' => $this->projet->id, 'montant' => 1000, 'motif' => 'Solde'], $this->tresorier))
        ->toThrow(DomainException::class, 'Jewrine national')
        ->and(fn () => $service->decider(['origine' => 'fonctionnement', 'destination' => 'fonctionnement', 'montant' => 1000, 'motif' => 'x'], $this->national))->toThrow(DomainException::class, 'différentes')
        ->and(fn () => $service->decider(['origine' => 'programme', 'destination' => 'fonctionnement', 'montant' => 1000, 'motif' => 'x'], $this->national))->toThrow(DomainException::class, 'programme')
        ->and(fn () => $service->decider(['origine' => 'fonctionnement', 'destination' => 'projet', 'destination_projet_id' => $this->projet->id, 'montant' => 1000, 'motif' => ' '], $this->national))->toThrow(DomainException::class, 'motif');

    $this->actingAs($this->national);
    Livewire::test(ListReaffectations::class)
        ->callAction('decider', ['origine' => 'programme', 'origine_collecte_id' => $this->magal->id, 'destination' => 'projet', 'destination_projet_id' => $this->projet->id, 'montant' => 4000, 'motif' => 'Le solde du Magal va au Daara de Médina'])
        ->assertHasNoActionErrors();
    $reaffectation = ReaffectationFonds::query()->firstOrFail();
    expect($reaffectation->numero)->toBe('RF-'.now()->format('Y').'-0001')
        ->and($service->peutEnregistrer($this->national, $reaffectation))->toBeFalse();
    Notification::assertSentTo($this->tresorier, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->tresorier)['titre'], 'Réaffectation à enregistrer'));

    $this->actingAs($this->tresorier);
    Livewire::test(ListReaffectations::class)->callAction(TestAction::make('enregistrer')->table($reaffectation))->assertHasNoActionErrors();
    expect($reaffectation->fresh()->statut)->toBe(ReaffectationFonds::ENREGISTREE)
        ->and($this->rapports->soldeProgramme($this->magal))->toBe(2000)
        ->and($this->rapports->soldeProjet($this->projet))->toBe(4000)
        ->and($this->rapports->rapport(now()->startOfYear(), now()->endOfYear())['reaffectations'][0]['destination'])->toBe('Projet : Daara de Médina');

    // Le fonds d'origine n'a plus que 2 000 FCFA : une seconde réaffectation de 3 000 est refusée ; une autre s'annule.
    $trop = $service->decider(['origine' => 'programme', 'origine_collecte_id' => $this->magal->id, 'destination' => 'fonctionnement', 'montant' => 3000, 'motif' => 'Frais'], $this->national);
    expect(fn () => $service->enregistrer($trop, $this->tresorier))->toThrow(DomainException::class, 'Solde insuffisant')
        ->and(fn () => $service->annuler($trop, $this->tresorier))->toThrow(DomainException::class);
    $service->annuler($trop, $this->national);
    expect($trop->fresh()->statut)->toBe(ReaffectationFonds::ANNULEE)
        ->and($this->rapports->soldeFonctionnement())->toBe(0);

    $this->actingAs($this->awa->user);
    expect(ReaffectationResource::canViewAny())->toBeFalse();
});

it('fait préparer la clôture par le Jewrine finance, confirmer par le Jewrine national, puis fige le bilan', function () {
    Notification::fake();
    $exercice = Exercice::query()->create(['libelle' => 'Essai', 'date_debut' => now()->subDays(20)->toDateString(), 'date_fin' => now()->subDays(2)->toDateString()]);
    $service = app(ExerciceService::class);

    $this->travel(-10)->days();
    app(CotisationService::class)->enregistrerEspeces($this->awa, 3, $this->tresorier);
    app(CotisationService::class)->declarer($this->modou, 'T_MODOU00CLOT001', 1);
    $this->travelBack();

    $alertes = $service->alertes($exercice);
    expect($alertes)->toHaveCount(1)->and($alertes[0])->toContain('restent à vérifier')
        ->and($service->peutPreparer($this->national, $exercice))->toBeFalse();

    $this->actingAs($this->tresorier);
    Livewire::test(ManageExercices::class)
        ->assertActionHidden(TestAction::make('confirmer')->table($exercice))
        ->callAction(TestAction::make('preparer')->table($exercice))
        ->assertHasNoActionErrors();
    expect($exercice->fresh()->cloture_preparee_par)->toBe($this->tresorier->id)
        ->and($exercice->fresh()->bilan['recettes'])->toBe(3000)
        ->and(fn () => $service->confirmer($exercice->fresh(), $this->tresorier))->toThrow(DomainException::class);
    Notification::assertSentTo($this->national, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->national)['titre'], 'Clôture à confirmer'));

    // Renvoyée avec un motif, puis préparée de nouveau et confirmée.
    expect(fn () => $service->renvoyer($exercice->fresh(), '', $this->national))->toThrow(DomainException::class, 'pourquoi');
    $service->renvoyer($exercice->fresh(), 'Vérifiez d\'abord la déclaration de Modou', $this->national);
    expect($exercice->fresh()->cloture_preparee_le)->toBeNull();
    $service->preparer($exercice->fresh(), $this->tresorier);

    $this->actingAs($this->national);
    Livewire::test(ManageExercices::class)->callAction(TestAction::make('confirmer')->table($exercice))->assertHasNoActionErrors();
    $clos = $exercice->fresh();
    expect($clos->estClos())->toBeTrue()
        ->and($clos->bilan['alertes'])->toHaveCount(1)
        ->and(ExerciceResource::canEdit($clos))->toBeFalse();

    // Une correction faite après la clôture ne change pas le bilan figé.
    $paiement = $this->awa->paiements()->firstOrFail();
    app(CorrectionPaiementService::class)->contrePasser($paiement, 'Erreur', $this->tresorier);
    expect($this->rapports->bilan($clos->fresh())['recettes'])->toBe(3000)
        ->and($this->rapports->rapport($clos->date_debut, $clos->date_fin)['recettes'])->toBe(3000)
        ->and($this->rapports->rapport(now()->startOfDay(), now())['recettes'])->toBe(-3000);

    // Le bilan en PDF, pour l'assemblée générale ; un simple membre n'y a pas accès.
    $this->actingAs($this->national)->get(route('finances.bilan', $clos))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(JournalExport::query()->where('document', 'like', 'Bilan de l\'exercice Essai%')->exists())->toBeTrue();
    $this->actingAs($this->awa->user)->get(route('finances.bilan', $clos))->assertForbidden();
});

it('montre les rapports financiers, le détail par membre à la Finance seulement, et exporte en Excel', function () {
    app(CotisationService::class)->enregistrerEspeces($this->awa, 2, $this->tresorier);
    payerOrdre(['montant' => 500, 'rattachement' => 'fonctionnement'], $this->tresorier, $this->national);

    $this->actingAs($this->tresorier);
    Livewire::test(RapportsFinanciers::class)
        ->assertSee('Rapports financiers')
        ->assertSee('Détail par membre')
        ->assertSee($this->awa->nom_complet)
        ->set('exercice', null)
        ->set('debut', now()->startOfMonth()->toDateString())
        ->set('fin', now()->endOfMonth()->toDateString())
        ->assertSee('2 000 FCFA')
        ->assertSee('500 FCFA');

    $this->actingAs($this->national);
    Livewire::test(RapportsFinanciers::class)->assertDontSee('Détail par membre');

    $reponse = $this->actingAs($this->tresorier)->get(route('finances.excel', ['debut' => now()->startOfYear()->toDateString(), 'fin' => now()->endOfYear()->toDateString(), 'membres' => 1]));
    $reponse->assertOk();
    expect($reponse->headers->get('content-disposition'))->toContain('.xlsx')
        ->and(JournalExport::query()->where('details', 'Avec le détail par membre')->exists())->toBeTrue();

    $this->actingAs($this->awa->user)->get(route('finances.excel', ['debut' => now()->toDateString(), 'fin' => now()->toDateString()]))->assertForbidden();
    expect(RapportsFinanciers::canAccess())->toBeFalse();
});

it('reprend les corrections déjà faites dans le journal des caisses', function () {
    expect(MouvementCaisse::NATURES)->toHaveKey('reaffectation');
    $paiement = app(CotisationService::class)->enregistrerEspeces($this->awa, 2, $this->tresorier);
    $avoir = app(CorrectionPaiementService::class)->versAvoir($paiement, 'Payé en trop', $this->tresorier);
    DB::table('mouvements_caisse')->where('nature', 'reaffectation')->delete();

    $migration = require database_path('migrations/2026_10_27_100055_reaffectations_exercices_bilan.php');
    (fn () => $this->reprendreLesCorrections())->call($migration);

    expect(MouvementCaisse::query()->where('nature', 'reaffectation')->where('avoir_id', $avoir->id)->value('montant'))->toBe(2000)
        ->and($this->generale->solde())->toBe(2000);
});
