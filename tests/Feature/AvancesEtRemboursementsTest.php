<?php

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Enums\TypeCaisse;
use App\Filament\Resources\Instances\Pages\ViewInstance;
use App\Filament\Resources\Instances\RelationManagers\CommissionsRelationManager;
use App\Filament\Resources\OrdresDepense\OrdreDepenseResource;
use App\Filament\Resources\OrdresDepense\Pages\ListOrdresDepense;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\CommissionComite;
use App\Models\LibelleDepense;
use App\Models\Membre;
use App\Models\MouvementCaisse;
use App\Models\OrdreDepense;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CollecteService;
use App\Services\ComiteService;
use App\Services\CotisationService;
use App\Services\FinanceRapportService;
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
function membreAvance(Cellule $cellule, string $prenom): Membre
{
    $compte = User::factory()->create(['name' => $prenom.' Diop']);
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->valide()->create(['cellule_id' => $cellule->id, 'prenom' => $prenom, 'valide_le' => now()->startOfMonth()->subMonths(6)]);
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->kaolack = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->national = nommerPourLeTest(membreAvance($this->kaolack, 'Babacar')->user, 'jewrine_national');
    $this->tresorier = nommerPourLeTest(membreAvance($this->kaolack, 'Fatou')->user, 'jewrine_finance');
    $this->president = membreAvance($this->kaolack, 'Serigne');
    $this->chef = membreAvance($this->kaolack, 'Awa');
    $this->suivi = membreAvance($this->kaolack, 'Moussa');
    app(ComiteService::class)->definirComiteDirecteur([$this->president->id], $this->super);

    $evenement = fn (string $titre, string $type): Collecte => app(ProgrammeService::class)->enregistrer([
        'titre' => $titre, 'type_programme_id' => typeProgramme($type), 'debut' => now()->addMonths(2)->format('Y-m-d 08:00'), 'lieu' => 'Touba',
        'mode' => ModeCollecte::Jayante->value, 'budget' => 1000000, 'organisation' => Collecte::COMITE, 'president_id' => $this->president->id,
        'ouverture' => now()->subDay()->toDateString(), 'statut' => StatutCollecte::Ouverte->value,
    ], null, $this->super);
    $this->magal = $evenement('Grand Magal 2026', 'magal');
    $this->ziar = $evenement('Grand Ziar 2026', 'ziar');
    $this->cuisine = CommissionComite::query()->create(['instance_id' => $this->magal->comite?->id, 'nom' => 'Cuisine', 'chef_id' => $this->chef->id]);
    $this->cars = CommissionComite::query()->create(['instance_id' => $this->ziar->comite?->id, 'nom' => 'Cars', 'chef_id' => $this->chef->id]);
    $this->ordres = app(OrdreDepenseService::class);
    $this->generale = app(TresorerieService::class)->caisse(TypeCaisse::Especes);
    $this->libelle = LibelleDepense::query()->where('nom', 'Achats de vivres')->firstOrFail();

    // De quoi payer : 100 000 FCFA en espèces dans la caisse générale.
    $payeur = membreAvance($this->kaolack, 'Binta');
    app(CollecteService::class)->fixerParMembre($this->magal, $payeur, 100000);
    app(CollecteService::class)->enregistrerEspeces($this->magal, $payeur, 100000, $this->tresorier);
});

/** Un ordre d'une commission, demandé par sa cheffe. */
function ordreDeCommission(CommissionComite $commission, User $chef, string $nature, int $montant, ?UploadedFile $piece = null): OrdreDepense
{
    return app(OrdreDepenseService::class)->demander([
        'objet' => 'Riz pour 300 personnes', 'libelle_depense_id' => LibelleDepense::query()->where('nom', 'Achats de vivres')->value('id'), 'montant' => $montant,
        'beneficiaire' => $nature === OrdreDepense::PAIEMENT ? 'Boutique Ndiaye' : '', 'nature' => $nature, 'rattachement' => 'programme', 'commission_comite_id' => $commission->id,
    ], $piece, $chef);
}

it('fait signer les ordres du Magal par le Jewrine du suivi des dépenses, ceux du Grand Ziar par le Jewrine national', function () {
    Notification::fake();
    $cdgmt = $this->magal->comite ?? throw new RuntimeException('Pas de comité.');

    // Tant que le Jewrine du suivi n'est pas nommé, le Jewrine national signe.
    $avant = ordreDeCommission($this->cuisine, $this->chef->user, OrdreDepense::PAIEMENT, 1000);
    expect($this->ordres->peutSigner($this->national, $avant))->toBeTrue();

    app(SuiviDepensesService::class)->designer($cdgmt, $this->suivi, $this->super);
    $jewrine = $this->suivi->user->fresh();
    $magal = ordreDeCommission($this->cuisine, $this->chef->user, OrdreDepense::PAIEMENT, 2000);
    $ziar = ordreDeCommission($this->cars, $this->chef->user, OrdreDepense::PAIEMENT, 3000);

    Notification::assertSentTo($jewrine, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->toArray($jewrine)['titre'], $magal->numero));
    Notification::assertNotSentTo($this->national, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->toArray($this->national)['titre'], $magal->numero));
    expect($this->ordres->peutSigner($jewrine, $magal))->toBeTrue()
        ->and($this->ordres->peutSigner($this->national, $magal))->toBeFalse()
        ->and($this->ordres->peutSigner($this->super, $magal))->toBeFalse()
        ->and($this->ordres->peutSigner($this->national, $ziar))->toBeTrue()
        ->and($this->ordres->peutSigner($jewrine, $ziar))->toBeFalse()
        ->and(fn () => $this->ordres->signer($magal, $this->national))->toThrow(DomainException::class, 'Jewrine du comité de suivi des dépenses');

    // Le Jewrine du suivi signe depuis le panneau ; il ne voit que les ordres de son comité.
    $this->actingAs($jewrine);
    expect(OrdreDepenseResource::canViewAny())->toBeTrue()
        ->and(OrdreDepenseResource::getEloquentQuery()->pluck('id')->all())->toEqualCanonicalizing([$avant->id, $magal->id])
        ->and(OrdreDepenseResource::getNavigationBadge())->toBe('2');
    Livewire::test(ListOrdresDepense::class)->callAction(TestAction::make('signer')->table($magal))->assertHasNoActionErrors();
    expect($magal->fresh()->signe_par)->toBe($jewrine->id)->and($magal->fresh()->statut)->toBe(OrdreDepense::SIGNE);

    // Jamais son propre ordre.
    $sien = $this->ordres->demander(['objet' => 'Carburant', 'libelle_depense_id' => $this->libelle->id, 'montant' => 500, 'beneficiaire' => 'Station',
        'rattachement' => 'programme', 'collecte_id' => $this->magal->id], null, $this->super);
    expect($this->ordres->peutSigner($jewrine, $sien))->toBeTrue();
    $propre = $this->ordres->demander(['objet' => 'Carburant', 'libelle_depense_id' => $this->libelle->id, 'montant' => 500, 'beneficiaire' => 'Station',
        'rattachement' => 'programme', 'collecte_id' => $this->magal->id], null, $this->tresorier);
    expect($this->ordres->peutSigner($this->tresorier, $propre))->toBeFalse();
});

it('fait justifier une avance par la cheffe et rendre le reste à la Finance, puis la solde', function () {
    Notification::fake();

    // La cheffe demande une avance de 50 000 FCFA depuis son téléphone (bénéficiaire : elle-même).
    $this->actingAs($this->chef->user)->post(route('comites.ordres.store', $this->cuisine), [
        'objet' => 'Riz et huile', 'libelle_depense_id' => $this->libelle->id, 'montant' => 50000, 'nature' => 'avance',
    ])->assertSessionHas('comite_succes');
    $avance = OrdreDepense::query()->where('nature', OrdreDepense::AVANCE)->firstOrFail();
    expect($avance->beneficiaire)->toBe('Awa Diop');

    $this->ordres->signer($avance, $this->national);
    $this->ordres->executer($avance->fresh(), $this->generale, UploadedFile::fake()->image('remise.jpg'), $this->tresorier);
    expect($this->generale->solde())->toBe(50000)->and($avance->fresh()->resteAJustifier())->toBe(50000);

    // Elle justifie 30 000 FCFA (photo du reçu), pas plus que le reste.
    $this->actingAs($this->chef->user)->get(route('comites.index'))->assertSee('reste à justifier ou à rendre')->assertSee('Justifier');
    $this->actingAs($this->chef->user)->post(route('comites.ordres.justifier', $avance), [
        'objet' => '10 sacs de riz', 'montant' => 30000, 'piece' => UploadedFile::fake()->image('recu.jpg'),
    ])->assertSessionHas('comite_succes', 'Pièce ajoutée. Reste à justifier ou à rendre : 20 000 FCFA.');
    $this->actingAs($this->chef->user)->post(route('comites.ordres.justifier', $avance), [
        'objet' => 'Huile', 'montant' => 25000, 'piece' => UploadedFile::fake()->image('recu2.jpg'),
    ])->assertSessionHas('comite_erreur');
    expect(fn () => $this->ordres->justifier($avance->fresh(), 'x', 100, UploadedFile::fake()->image('x.jpg'), membreAvance($this->kaolack, 'Ibou')->user))->toThrow(DomainException::class, 'reçu l\'avance');

    // La Finance enregistre les 20 000 FCFA rendus : l'avance est soldée.
    expect(fn () => $this->ordres->enregistrerRetour($avance->fresh(), 20000, $this->generale, $this->chef->user))->toThrow(DomainException::class, 'Finance');
    $this->actingAs($this->tresorier);
    Livewire::test(ListOrdresDepense::class)
        ->callAction(TestAction::make('retour')->table($avance), data: ['montant' => 20000, 'caisse_id' => $this->generale->id])
        ->assertHasNoActionErrors();

    $avance->refresh();
    expect($avance->montant_rendu)->toBe(20000)
        ->and($avance->soldee_le)->not->toBeNull()
        ->and($avance->decaisse())->toBe(30000)
        ->and($avance->justifie())->toBe(30000)
        ->and($this->generale->solde())->toBe(70000)
        ->and(MouvementCaisse::query()->where('nature', 'retour')->value('montant'))->toBe(20000)
        ->and($this->cuisine->fresh()->depense())->toBe(30000)
        ->and($this->cuisine->fresh()->resteAJustifier())->toBe(0)
        ->and(app(FinanceRapportService::class)->rapport(now()->startOfMonth(), now()->endOfMonth())['depenses'])->toBe(30000)
        ->and(app(FinanceRapportService::class)->soldeProgramme($this->magal))->toBe(70000)
        ->and($this->ordres->peutJustifier($this->chef->user, $avance))->toBeFalse();
    Notification::assertSentTo($this->chef->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->chef->user)['titre'], 'Avance soldée'));

    // L'espace montre décaissé, justifié et reste à justifier.
    $this->actingAs($this->national);
    Livewire::test(CommissionsRelationManager::class, ['ownerRecord' => $this->magal->comite, 'pageClass' => ViewInstance::class])
        ->assertSee('Justifié')->assertSee('Reste à justifier')->assertSee('30 000 FCFA');
});

it('rembourse la cheffe sur reçu, et prévient quand une commission dépasse son budget', function () {
    Notification::fake();
    expect(fn () => ordreDeCommission($this->cuisine, $this->chef->user, OrdreDepense::REMBOURSEMENT, 15000))->toThrow(DomainException::class, 'reçu')
        ->and(fn () => ordreDeCommission($this->cuisine, $this->chef->user, 'cadeau', 15000))->toThrow(DomainException::class, 'nature');

    app(SuiviDepensesService::class)->arbitrer($this->cuisine, 10000, 'Petit budget', $this->super);
    $remboursement = ordreDeCommission($this->cuisine, $this->chef->user, OrdreDepense::REMBOURSEMENT, 15000, UploadedFile::fake()->image('recu.jpg'));
    $this->ordres->signer($remboursement, $this->national);
    $this->ordres->executer($remboursement->fresh(), $this->generale, UploadedFile::fake()->image('versement.jpg'), $this->tresorier);

    $remboursement->refresh();
    expect($remboursement->justifie())->toBe(15000)
        ->and($remboursement->resteAJustifier())->toBe(0)
        ->and($this->ordres->peutEnregistrerRetour($this->tresorier, $remboursement))->toBeFalse();
    Notification::assertSentTo($this->president->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->president->user)['titre'], 'Budget dépassé : commission Cuisine'));

    // Un ordre de cotisation remboursée (R3c) reste un paiement direct.
    app(CotisationService::class)->enregistrerEspeces($this->chef, 1, $this->tresorier);
    expect(OrdreDepense::query()->where('nature', OrdreDepense::PAIEMENT)->count())->toBe(0);
});
