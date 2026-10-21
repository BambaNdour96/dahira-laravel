<?php

use App\Enums\ModeCollecte;
use App\Enums\MoyenPaiement;
use App\Enums\Sexe;
use App\Enums\StatutCollecte;
use App\Filament\Resources\Cellules\Pages\ManageCellules;
use App\Filament\Resources\Collectes\Pages\EditCollecte;
use App\Filament\Resources\Collectes\Pages\ViewCollecte;
use App\Filament\Resources\Dahiras\Pages\ListDahiras;
use App\Filament\Resources\Instances\Widgets\AvancementCommissions;
use App\Filament\Resources\Pointeurs\Pages\ManagePointeurs;
use App\Filament\Resources\Projets\Widgets\EtapesDuProjet;
use App\Filament\Resources\Reunions\Pages\ViewReunion;
use App\Filament\Resources\Sympathisants\Pages\ManageSympathisants;
use App\Models\ActionComite;
use App\Models\Cellule;
use App\Models\CellulePointeur;
use App\Models\Collecte;
use App\Models\CommissionComite;
use App\Models\Decision;
use App\Models\Evenement;
use App\Models\Instance;
use App\Models\Jalon;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Projet;
use App\Models\RepresentantFinance;
use App\Models\Reunion;
use App\Models\Sympathisant;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\AssiduiteService;
use App\Services\ComiteService;
use App\Services\ConservationService;
use App\Services\PointageService;
use App\Services\RepresentantFinanceService;
use App\Services\SympathisantService;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function membreConserve(Cellule $cellule, Sexe $sexe = Sexe::Homme, string $prenom = 'Moussa'): Membre
{
    $compte = User::factory()->create();
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->for($cellule)->valide()->create(['prenom' => $prenom, 'sexe' => $sexe]);
}

function programmeConserve(array $attributs = []): Collecte
{
    return Collecte::query()->create(array_merge([
        'titre' => 'Magal de Touba 2026',
        'type_programme_id' => typeProgramme('magal'),
        'mode' => ModeCollecte::Jayante,
        'ouverture' => now()->subDay(),
        'date_limite' => now()->addMonth(),
        'statut' => StatutCollecte::Ouverte,
    ], $attributs));
}

function dahiraConservee(Cellule $cellule, array $attributs = []): Evenement
{
    return Evenement::query()->create(array_merge([
        'type' => Evenement::DAHIRA, 'titre' => 'Dahira', 'lieu' => 'Médina', 'debut' => now(), 'cellule_id' => $cellule->id,
    ], $attributs));
}

function reunionConservee(Instance $instance, array $attributs = []): Reunion
{
    return Reunion::query()->create(array_merge([
        'instance_id' => $instance->id, 'titre' => 'Réunion de préparation', 'debut' => now()->addDays(3), 'lieu' => 'Siège',
    ], $attributs));
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->kaolack = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->service = app(ConservationService::class);
});

describe('la base de données', function () {
    it('refuse d\'effacer un membre qui a des paiements, même en SQL direct', function () {
        $membre = membreConserve($this->kaolack);
        Paiement::query()->create([
            'membre_id' => $membre->id, 'objet' => 'frais_carte', 'montant' => 1000,
            'moyen' => MoyenPaiement::Especes, 'statut' => 'confirme', 'declare_le' => now(),
        ]);

        // Un point de sauvegarde : sous PostgreSQL, une erreur annule sinon toute la transaction du test.
        expect(fn () => DB::transaction(fn () => DB::table('membres')->where('id', $membre->id)->delete()))->toThrow(QueryException::class)
            ->and(Paiement::query()->where('membre_id', $membre->id)->exists())->toBeTrue();
    });

    it('refuse d\'effacer un comité qui a des commissions', function () {
        $instance = Instance::query()->create(['type' => Instance::CDGMT, 'nom' => 'CDGMT 2026', 'code' => 'CDGMT', 'annee' => 2026]);
        CommissionComite::query()->create(['instance_id' => $instance->id, 'nom' => 'Cuisine']);

        expect(fn () => DB::transaction(fn () => DB::table('instances')->where('id', $instance->id)->delete()))->toThrow(QueryException::class);
    });

    it('refuse toute suppression des données importantes par l\'application', function () {
        $programme = programmeConserve();
        $instance = Instance::query()->create(['type' => Instance::CDGMT, 'nom' => 'CDGMT 2026', 'code' => 'CDGMT', 'annee' => 2026]);
        $reunion = reunionConservee($instance);
        $decision = Decision::query()->create(['instance_id' => $instance->id, 'reunion_id' => $reunion->id, 'texte' => 'Le Magal se tiendra à Touba.']);
        $action = ActionComite::query()->create(['instance_id' => $instance->id, 'reunion_id' => $reunion->id, 'description' => 'Réserver les cars']);

        foreach ([$programme, $instance, $reunion, $decision, $action, $this->kaolack, membreConserve($this->kaolack), $this->super] as $donnee) {
            expect(fn () => $donnee->delete())->toThrow(DomainException::class);
        }

        expect(Decision::query()->whereKey($decision->id)->exists())->toBeTrue();
    });
});

describe('les programmes', function () {
    it('annule un programme sans versement, avec motif et auteur', function () {
        $programme = programmeConserve(['statut' => StatutCollecte::Brouillon]);
        $this->actingAs($this->super);

        Livewire::test(ViewCollecte::class, ['record' => $programme->id])
            ->callAction('annuler', data: ['motif' => 'Programme créé en double']);

        $programme->refresh();
        expect($programme->statut)->toBe(StatutCollecte::Annulee)
            ->and($programme->motif_annulation)->toBe('Programme créé en double')
            ->and($programme->annule_par)->toBe($this->super->id)
            ->and($this->super->can('update', $programme))->toBeFalse()
            ->and($this->super->can('delete', $programme))->toBeFalse();

        Livewire::test(ViewCollecte::class, ['record' => $programme->id])->assertActionHidden('annuler');
    });

    it('refuse d\'annuler un programme qui a reçu un versement : il se clôture', function () {
        $programme = programmeConserve();
        Paiement::query()->create([
            'membre_id' => membreConserve($this->kaolack)->id, 'collecte_id' => $programme->id, 'objet' => 'collecte',
            'montant' => 5000, 'moyen' => MoyenPaiement::Especes, 'statut' => 'confirme', 'declare_le' => now(),
        ]);

        expect(fn () => $this->service->annulerProgramme($programme, 'Erreur', $this->super))->toThrow(DomainException::class, 'clôturez-le')
            ->and($programme->refresh()->statut)->toBe(StatutCollecte::Ouverte);
    });

    it('n\'offre plus de bouton Supprimer sur la page de paramètres', function () {
        $this->actingAs($this->super);

        Livewire::test(EditCollecte::class, ['record' => programmeConserve()->id])
            ->assertActionDoesNotExist('delete')
            ->assertActionVisible('annuler');
    });
});

describe('les dahiras et les événements', function () {
    beforeEach(function () {
        $this->jewrine = membreConserve($this->kaolack, Sexe::Homme, 'Ibrahima');
        $this->jewrineCompte = User::query()->findOrFail($this->jewrine->user_id);
        $this->jewrineCompte->assignRole('jewrine_cellule');
        nommerPourLeTest($this->jewrineCompte, 'jewrine_cellule', $this->kaolack);
        $this->awa = membreConserve($this->kaolack, Sexe::Femme, 'Awa');
    });

    it('annule la dahira du jour, prévient les membres et la retire du pointage et de l\'assiduité', function () {
        Notification::fake();
        $dahira = dahiraConservee($this->kaolack);
        $this->actingAs($this->jewrineCompte);

        Livewire::test(ListDahiras::class)
            ->assertActionDoesNotExist(TestAction::make('delete')->table($dahira))
            ->callAction(TestAction::make('annuler')->table($dahira), data: ['motif' => 'Décès dans la cellule']);

        expect($dahira->refresh()->estAnnule())->toBeTrue()
            ->and($dahira->scanOuvert())->toBeFalse()
            ->and(app(PointageService::class)->rendezVousDuMoment($this->jewrineCompte))->toHaveCount(0)
            ->and(app(AssiduiteService::class)->prochaineDahira($this->awa))->toBeNull();

        Notification::assertSentTo(
            User::query()->findOrFail($this->awa->user_id),
            NotificationMembre::class,
            fn (NotificationMembre $avis): bool => $avis->titre === 'Dahira annulée' && str_contains($avis->message, 'Décès dans la cellule'),
        );
    });

    it('ne prévient personne pour une dahira annulée à l\'avance', function () {
        Notification::fake();
        $this->service->annulerEvenement(dahiraConservee($this->kaolack, ['debut' => now()->addWeek()]), 'Report', $this->jewrineCompte);

        Notification::assertNothingSent();
    });

    it('refuse d\'annuler une activité qui a eu lieu', function () {
        $dahira = dahiraConservee($this->kaolack);
        app(PointageService::class)->pointer($dahira, $this->awa, $this->super, 'manuel');

        expect(fn () => $this->service->annulerEvenement($dahira, 'Erreur', $this->super))->toThrow(DomainException::class, 'a eu lieu');
    });

    it('garde la trace d\'une présence retirée par correction', function () {
        $dahira = dahiraConservee($this->kaolack);
        $pointage = app(PointageService::class);
        $pointage->pointer($dahira, $this->awa, $this->super, 'manuel');
        $this->actingAs($this->super);

        $pointage->retirer($dahira, $this->awa, $this->super);

        $trace = JournalActivite::query()->where('sujet_type', $dahira->getMorphClass())->where('sujet_id', $dahira->id)->latest('id')->firstOrFail();
        expect($dahira->presences()->count())->toBe(0)
            ->and($trace->resume())->toContain('Awa')->toContain('retiré');
    });
});

describe('les réunions', function () {
    beforeEach(function () {
        $this->instance = Instance::query()->create(['type' => Instance::CDGMT, 'nom' => 'CDGMT 2026', 'code' => 'CDGMT', 'annee' => 2026]);
        $this->membre = membreConserve($this->kaolack, Sexe::Homme, 'Modou');
        $this->instance->membres()->attach($this->membre->id);
        $this->comites = app(ComiteService::class);
    });

    it('annule une réunion convoquée, prévient les membres et la fige', function () {
        Notification::fake();
        $reunion = reunionConservee($this->instance);
        $this->comites->convoquer($reunion);
        $this->actingAs($this->super);

        Livewire::test(ViewReunion::class, ['record' => $reunion->id])
            ->callAction('annuler', data: ['motif' => 'Le président est en voyage']);

        $reunion->refresh();
        expect($reunion->statut)->toBe('annulee')
            ->and(ComiteService::peutRediger($this->super, $reunion))->toBeFalse()
            ->and(fn () => $this->comites->convoquer($reunion))->toThrow(DomainException::class);

        Notification::assertSentTo(
            User::query()->findOrFail($this->membre->user_id),
            NotificationMembre::class,
            fn (NotificationMembre $avis): bool => str_starts_with($avis->titre, 'Réunion annulée'),
        );
    });

    it('refuse d\'annuler une réunion dont le PV est publié', function () {
        $reunion = reunionConservee($this->instance, ['statut' => 'publiee']);

        expect(fn () => $this->service->annulerReunion($reunion, 'Erreur', $this->super))->toThrow(DomainException::class, 'publié');
    });

    it('archive une commission vide, jamais une commission qui a des membres', function () {
        $vide = CommissionComite::query()->create(['instance_id' => $this->instance->id, 'nom' => 'Doublon']);
        $occupee = CommissionComite::query()->create(['instance_id' => $this->instance->id, 'nom' => 'Cuisine']);
        $occupee->membres()->attach($this->membre->id);
        $this->actingAs($this->super);

        Livewire::test(AvancementCommissions::class, ['record' => $this->instance])
            ->callAction(TestAction::make('archiver')->table($vide));

        expect($vide->refresh()->estArchive())->toBeTrue()
            ->and($this->instance->commissions()->pluck('nom')->all())->toBe(['Cuisine'])
            ->and(fn () => $this->service->archiver($occupee, $this->super))->toThrow(DomainException::class, 'vide');
    });

    it('note au journal le départ d\'un membre de commission', function () {
        $commission = CommissionComite::query()->create(['instance_id' => $this->instance->id, 'nom' => 'Cuisine']);
        $this->actingAs($this->super);
        $this->comites->ajouterMembreCommission($commission, $this->membre, $this->super);
        $this->comites->retirerMembreCommission($commission, $this->membre, $this->super);

        expect(JournalActivite::query()->where('sujet_type', $commission->getMorphClass())->where('sujet_id', $commission->id)->get()->map->resume()->implode(' | '))
            ->toContain('Modou')->toContain('ajouté(e)')->toContain('retiré(e)');
    });
});

describe('les cellules, projets et désignations', function () {
    it('archive une cellule vide, qui disparaît de l\'inscription', function () {
        $vide = Cellule::factory()->create(['nom' => 'Ancienne cellule']);
        membreConserve($this->kaolack);
        $this->actingAs($this->super);

        Livewire::test(ManageCellules::class)
            ->assertActionDoesNotExist(TestAction::make('delete')->table($vide))
            ->callAction(TestAction::make('archiver')->table($vide));

        expect($vide->refresh()->estArchive())->toBeTrue()
            ->and(fn () => $this->service->archiver($this->kaolack, $this->super))->toThrow(DomainException::class, 'membres');

        auth()->logout();
        $this->get(route('inscription'))->assertOk()->assertSee('Kaolack')->assertDontSee('Ancienne cellule');
    });

    it('archive une étape de projet au lieu de l\'effacer', function () {
        $projet = Projet::query()->create([
            'titre' => 'Salle de lecture', 'objectif' => 'Une salle', 'date_debut' => now()->subMonth(), 'date_fin' => now()->addMonth(),
        ]);
        $etape = $projet->jalons()->create(['titre' => 'Fondations', 'echeance' => now()->subDay()]);
        $this->actingAs($this->super);

        Livewire::test(EtapesDuProjet::class, ['record' => $projet])
            ->callAction(TestAction::make('archiver')->table($etape))
            ->assertDontSee('Fondations');

        expect(Jalon::query()->whereKey($etape->id)->exists())->toBeTrue()
            ->and($projet->jalonsEnRetard())->toBe(0)
            ->and($this->super->can('delete', $projet))->toBeFalse();
    });

    it('met fin à un pointeur : il ne pointe plus, l\'historique reste et il peut être redésigné', function () {
        $pointeur = membreConserve($this->kaolack, Sexe::Homme, 'Cheikh');
        $compte = User::query()->findOrFail($pointeur->user_id);
        $designation = CellulePointeur::query()->create(['cellule_id' => $this->kaolack->id, 'user_id' => $compte->id, 'perimetre' => 'hommes']);
        $dahira = dahiraConservee($this->kaolack);
        $this->actingAs($this->super);

        expect(app(PointageService::class)->perimetre($compte, $dahira))->toBe('hommes');

        Livewire::test(ManagePointeurs::class)->callAction(TestAction::make('mettreFin')->table($designation));

        expect(app(PointageService::class)->perimetre($compte, $dahira))->toBeNull()
            ->and(CellulePointeur::query()->withoutGlobalScope(CellulePointeur::EN_COURS)->whereKey($designation->id)->value('fin_le'))->not->toBeNull();

        CellulePointeur::query()->create(['cellule_id' => $this->kaolack->id, 'user_id' => $compte->id, 'perimetre' => 'hommes']);
        expect(CellulePointeur::query()->withoutGlobalScope(CellulePointeur::EN_COURS)->where('user_id', $compte->id)->count())->toBe(2);
    });

    it('met fin à un représentant finance sans effacer sa désignation', function () {
        $tresorier = User::factory()->create();
        $tresorier->assignRole('jewrine_finance');
        $awa = membreConserve($this->kaolack, Sexe::Femme, 'Awa');
        $fatou = membreConserve($this->kaolack, Sexe::Femme, 'Fatou');
        $representants = app(RepresentantFinanceService::class);

        $premiere = $representants->designer($this->kaolack, 'femmes', User::query()->findOrFail($awa->user_id), $tresorier);
        $representants->designer($this->kaolack, 'femmes', User::query()->findOrFail($fatou->user_id), $tresorier);

        expect(RepresentantFinance::query()->withoutGlobalScope(RepresentantFinance::EN_COURS)->count())->toBe(2)
            ->and($premiere->refresh()->estTerminee())->toBeTrue()
            ->and(RepresentantFinance::pour($awa)?->user_id)->toBe($fatou->user_id)
            ->and(User::query()->findOrFail($awa->user_id)->hasRole('representant_finance'))->toBeFalse();
    });
});

describe('les sympathisants', function () {
    it('retire un sympathisant du registre en effaçant son nom, pas ses participations', function () {
        $jewrine = membreConserve($this->kaolack, Sexe::Homme, 'Ibrahima');
        $compte = User::query()->findOrFail($jewrine->user_id);
        $compte->assignRole('jewrine_cellule');
        nommerPourLeTest($compte, 'jewrine_cellule', $this->kaolack);
        $dahira = dahiraConservee($this->kaolack);
        $sympathisant = app(SympathisantService::class)->accueillir($dahira, ['prenom' => 'Ali', 'nom' => 'Sarr', 'sexe' => 'H', 'telephone' => '771234567', 'accord' => true], $compte);
        $programme = programmeConserve();
        app(SympathisantService::class)->participer($programme, $sympathisant, 5000, MoyenPaiement::Especes, $this->super);
        $this->actingAs($compte);

        Livewire::test(ManageSympathisants::class)
            ->assertActionDoesNotExist(TestAction::make('delete')->table($sympathisant))
            ->callAction(TestAction::make('retirer')->table($sympathisant));

        $sympathisant->refresh();
        expect($sympathisant->nomComplet())->toBe('Sympathisant anonyme')
            ->and($sympathisant->telephone)->toBeNull()
            ->and($sympathisant->participations()->sum('montant'))->toBe(5000)
            ->and($programme->totalSympathisants())->toBe(5000)
            ->and(Sympathisant::query()->auRegistre()->count())->toBe(0);
    });
});
