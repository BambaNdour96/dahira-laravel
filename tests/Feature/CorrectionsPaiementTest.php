<?php

use App\Enums\ModeCollecte;
use App\Enums\MoyenPaiement;
use App\Enums\ProgrammeCollecte;
use App\Enums\Sexe;
use App\Enums\StatutCollecte;
use App\Enums\StatutPaiement;
use App\Enums\TypeCaisse;
use App\Filament\Resources\Avoirs\Pages\ListAvoirs;
use App\Filament\Resources\Paiements\Pages\ManagePaiements;
use App\Models\Avoir;
use App\Models\Cellule;
use App\Models\Collecte;
use App\Models\CorrectionPaiement;
use App\Models\Cotisation;
use App\Models\LibelleDepense;
use App\Models\Membre;
use App\Models\MouvementCaisse;
use App\Models\Paiement;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CollecteService;
use App\Services\CorrectionPaiementService;
use App\Services\CotisationService;
use App\Services\OrdreDepenseService;
use App\Services\PaiementService;
use App\Services\RepresentantFinanceService;
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

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->cellule = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->tresorier = User::factory()->create(['name' => 'Fatou Ndiaye']);
    $this->tresorier->assignRole('jewrine_finance');
    $this->national = nommerPourLeTest(User::factory()->create(), 'jewrine_national');

    $compte = User::factory()->create();
    $compte->assignRole('membre');
    Membre::factory()->for($compte)->valide()->create(['cellule_id' => $this->cellule->id, 'sexe' => Sexe::Femme]);
    $this->representante = $compte;
    app(RepresentantFinanceService::class)->designer($this->cellule, 'femmes', $this->representante, $this->tresorier);

    $awa = User::factory()->create();
    $awa->assignRole('membre');
    $this->awa = Membre::factory()->for($awa)->valide()->create(['cellule_id' => $this->cellule->id, 'sexe' => Sexe::Femme, 'valide_le' => now()->startOfMonth()->subMonths(4)]);

    $this->cotisations = app(CotisationService::class);
    $this->service = app(CorrectionPaiementService::class);
    $this->tresorerie = app(TresorerieService::class);
});

it('contre-passe un paiement : l\'argent sort de sa caisse, les mois redeviennent dus, le reçu porte « Annulé »', function () {
    Notification::fake();
    $paiement = $this->cotisations->enregistrerEspeces($this->awa, 3, $this->representante);
    $caisse = $this->tresorerie->caisseDuRepresentant($this->representante);
    expect($caisse->solde())->toBe(3000)->and(count($this->cotisations->moisEnRetard($this->awa)))->toBe(0);

    expect(fn () => $this->service->contrePasser($paiement, ' ', $this->tresorier))->toThrow(DomainException::class, 'motif')
        ->and(fn () => $this->service->contrePasser($paiement, 'Erreur', $this->national))->toThrow(DomainException::class, 'Jewrine finance national');

    $this->actingAs($this->tresorier);
    Livewire::test(ManagePaiements::class)
        ->set('activeTab', 'confirmes')
        ->filterTable('statut', StatutPaiement::Confirme->value)
        ->callAction(TestAction::make('contrePasser')->table($paiement), data: ['motif' => 'Saisi deux fois'])
        ->assertHasNoActionErrors();

    expect($paiement->fresh()->statut)->toBe(StatutPaiement::Contrepasse)
        ->and($caisse->solde())->toBe(0)
        ->and(count($this->cotisations->moisEnRetard($this->awa->fresh())))->toBe(3)
        ->and(Cotisation::query()->withoutGlobalScope(Cotisation::EN_VIGUEUR)->where('paiement_id', $paiement->id)->whereNotNull('annulee_le')->count())->toBe(3)
        ->and(CorrectionPaiement::query()->where('type', 'contre_passation')->exists())->toBeTrue();
    Notification::assertSentTo($this->awa->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->awa->user)['titre'] === 'Paiement annulé');

    // Les mêmes mois se règlent de nouveau, et le reçu annulé reste lisible.
    $this->cotisations->enregistrerEspeces($this->awa->fresh(), 3, $this->representante);
    expect(count($this->cotisations->moisEnRetard($this->awa->fresh())))->toBe(0);
    $this->actingAs($this->awa->user)->get(route('recus.show', $paiement))->assertOk();
});

it('retire l\'argent de la caisse générale si le représentant l\'a déjà remis, et refuse si le solde ne suffit pas', function () {
    $paiement = $this->cotisations->enregistrerEspeces($this->awa, 2, $this->representante);
    app(RepresentantFinanceService::class)->recevoirRemise($this->representante, $this->tresorier);
    $generale = $this->tresorerie->caisse(TypeCaisse::Especes);
    expect($generale->solde())->toBe(2000);

    // Une dépense vide la caisse générale : la contre-passation est refusée.
    $ordre = app(OrdreDepenseService::class)->demander(['objet' => 'Achat', 'libelle_depense_id' => LibelleDepense::query()->value('id'), 'montant' => 1500, 'beneficiaire' => 'Boutique', 'rattachement' => 'fonctionnement'], null, $this->tresorier);
    app(OrdreDepenseService::class)->signer($ordre, $this->national);
    app(OrdreDepenseService::class)->executer($ordre->fresh(), $generale, UploadedFile::fake()->image('r.jpg'), $this->tresorier);
    expect(fn () => $this->service->contrePasser($paiement->fresh(), 'Erreur', $this->tresorier))->toThrow(DomainException::class, 'Solde insuffisant dans « Caisse générale »');

    $this->cotisations->enregistrerEspeces(Membre::factory()->valide()->create(['valide_le' => now()->subMonths(2)]), 2, $this->tresorier);
    $this->service->contrePasser($paiement->fresh(), 'Erreur', $this->tresorier);
    expect($generale->solde())->toBe(500)
        ->and($this->tresorerie->caisseDuRepresentant($this->representante)->solde())->toBe(0);
});

it('réaffecte une cotisation vers un Jàyante : nouveau reçu, l\'argent reste dans sa caisse', function () {
    $collecte = Collecte::query()->create(['titre' => 'Magal 2026', 'programme' => ProgrammeCollecte::Magal, 'mode' => ModeCollecte::EngagementLibre, 'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte]);
    $engagement = app(CollecteService::class)->fixerParMembre($collecte, $this->awa, 10000);
    $paiement = $this->cotisations->enregistrerEspeces($this->awa, 2, $this->representante);
    $caisse = $this->tresorerie->caisseDuRepresentant($this->representante);

    expect(fn () => $this->service->reaffecter($paiement, ['objet' => 'collecte', 'collecte_id' => $collecte->id], '', $this->tresorier))->toThrow(DomainException::class, 'motif');
    $this->service->reaffecter($paiement, ['objet' => 'collecte', 'collecte_id' => $collecte->id], 'Le membre voulait payer son Jàyante', $this->tresorier);

    $nouveau = Paiement::query()->where('objet', 'collecte')->firstOrFail();
    expect($paiement->fresh()->statut)->toBe(StatutPaiement::Contrepasse)
        ->and($nouveau->numero_recu)->toStartWith('EVT-')
        ->and($nouveau->caisse_id)->toBe($caisse->id)
        ->and($caisse->solde())->toBe(2000)
        ->and($engagement->fresh()->verse())->toBe(2000)
        ->and(count($this->cotisations->moisEnRetard($this->awa->fresh())))->toBe(3);

    // Vers une cotisation : des mois entiers seulement.
    $versement = app(CollecteService::class)->enregistrerEspeces($collecte, $this->awa, 1500, $this->representante);
    expect(fn () => $this->service->reaffecter($versement, ['objet' => 'cotisation'], 'Erreur d\'objet', $this->tresorier))->toThrow(DomainException::class, 'mois entiers');
});

it('transforme un paiement en avoir, que la Finance impute à la main, sans changer le solde de la caisse', function () {
    $paiement = $this->cotisations->enregistrerEspeces($this->awa, 2, $this->representante);
    $avoir = $this->service->versAvoir($paiement, 'Payé en trop', $this->tresorier);
    $caisse = $this->tresorerie->caisseDuRepresentant($this->representante);
    expect($caisse->solde())->toBe(2000)
        ->and(MouvementCaisse::query()->where('avoir_id', $avoir->id)->where('nature', 'reaffectation')->value('sens'))->toBe(MouvementCaisse::ENTREE);

    expect($avoir->reste)->toBe(2000);
    $this->actingAs($this->awa->user)->get(route('cotisations'))->assertSee('un avoir de');

    $this->actingAs($this->tresorier);
    Livewire::test(ListAvoirs::class)
        ->callAction(TestAction::make('imputer')->table($avoir), data: ['objet' => 'cotisation', 'montant' => 1000])
        ->assertHasNoActionErrors();

    $impute = Paiement::query()->where('moyen', MoyenPaiement::Avoir->value)->firstOrFail();
    expect($avoir->fresh()->reste)->toBe(1000)
        ->and($impute->avoir_id)->toBe($avoir->id)
        ->and($impute->caisse_id)->toBeNull()
        ->and($caisse->solde())->toBe(2000)
        ->and(MouvementCaisse::query()->where('paiement_id', $impute->id)->where('sens', MouvementCaisse::ENTREE)->value('montant'))->toBe(1000)
        ->and(fn () => $this->service->imputerAvoir($avoir->fresh(), ['objet' => 'cotisation'], 5000, $this->tresorier))->toThrow(DomainException::class, 'Le montant imputé');

    // Annuler un paiement par avoir rend son montant à l'avoir.
    $this->service->contrePasser($impute, 'Mauvais membre', $this->tresorier);
    expect($avoir->fresh()->reste)->toBe(2000)->and($caisse->solde())->toBe(2000);
});

it('rembourse par un ordre de dépense : le paiement n\'est annulé qu\'une fois l\'ordre payé', function () {
    Notification::fake();
    app(PaiementService::class)->confirmer($this->cotisations->declarer($this->awa, 'T_AWA0000REMB001', 3, null, $this->awa->user), $this->tresorier);
    $paiement = Paiement::query()->where('reference', 'T_AWA0000REMB001')->firstOrFail();
    $wave = $this->tresorerie->caisse(TypeCaisse::Wave);
    expect($wave->solde())->toBe(3000);

    $correction = $this->service->rembourser($paiement, 'Le membre quitte la Daara', $this->tresorier);
    $ordre = $correction->ordre;
    expect($ordre)->not->toBeNull()
        ->and($ordre?->beneficiaire)->toBe($this->awa->nom_complet)
        ->and($paiement->fresh()->statut)->toBe(StatutPaiement::Confirme)
        ->and(CorrectionPaiementService::peutCorriger($this->tresorier, $paiement->fresh()))->toBeFalse();

    app(OrdreDepenseService::class)->signer($ordre, $this->national);
    // Option A : le Jewrine finance paie l'ordre qu'il a demandé, une fois signé.
    app(OrdreDepenseService::class)->executer($ordre->fresh(), $wave, UploadedFile::fake()->image('virement.jpg'), $this->tresorier);

    expect($paiement->fresh()->statut)->toBe(StatutPaiement::Contrepasse)
        ->and($correction->fresh()->statut)->toBe(CorrectionPaiement::FAITE)
        ->and($wave->solde())->toBe(0)
        ->and(count($this->cotisations->moisEnRetard($this->awa->fresh())))->toBe(3);
    Notification::assertSentTo($this->awa->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->awa->user)['titre'] === 'Paiement remboursé');

    // Un ordre de remboursement refusé abandonne la correction ; le paiement reste valide.
    $autre = $this->cotisations->enregistrerEspeces($this->awa->fresh(), 1, $this->representante);
    $abandon = $this->service->rembourser($autre, 'Erreur', $this->tresorier);
    app(OrdreDepenseService::class)->refuser($abandon->ordre ?? throw new RuntimeException, 'Pas de remboursement', $this->national);
    expect($abandon->fresh()->statut)->toBe(CorrectionPaiement::ABANDONNEE)->and($autre->fresh()->statut)->toBe(StatutPaiement::Confirme);
});

it('ne corrige ni les frais de carte ni un paiement non validé', function () {
    $frais = Paiement::query()->create(['membre_id' => $this->awa->id, 'objet' => 'frais_carte', 'montant' => 1000, 'moyen' => MoyenPaiement::Especes, 'statut' => StatutPaiement::Confirme, 'declare_le' => now(), 'traite_le' => now(), 'traite_par' => $this->tresorier->id]);
    $declare = $this->cotisations->declarer($this->awa, 'T_AWA0000DECL001', 1);

    expect(CorrectionPaiementService::peutCorriger($this->tresorier, $frais))->toBeFalse()
        ->and(CorrectionPaiementService::peutCorriger($this->tresorier, $declare))->toBeFalse()
        ->and(Avoir::query()->count())->toBe(0);
});
