<?php

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Enums\StatutMembre;
use App\Filament\Resources\ChangementsCellule\Pages\ListChangementsCellule;
use App\Filament\Resources\Competences\CompetenceResource;
use App\Filament\Resources\Membres\Pages\EditMembre;
use App\Filament\Resources\Membres\Widgets\HistoriqueCellulesDuMembre;
use App\Models\Cellule;
use App\Models\CellulePointeur;
use App\Models\ChangementCellule;
use App\Models\Collecte;
use App\Models\CommissionComite;
use App\Models\Competence;
use App\Models\HistoriqueCellule;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\Nomination;
use App\Models\Paiement;
use App\Models\User;
use App\Models\Volontariat;
use App\Notifications\NotificationMembre;
use App\Services\ChangementCelluleService;
use App\Services\CollecteService;
use App\Services\CompetencesService;
use App\Services\FamilleService;
use App\Services\PaiementService;
use App\Services\StatutMembreService;
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

/** Un membre validé, avec son compte. */
function membreR2b(string $prenom, Cellule $cellule): Membre
{
    $compte = User::factory()->create(['name' => $prenom.' Fall']);
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->valide()->create(['prenom' => $prenom, 'cellule_id' => $cellule->id, 'valide_le' => now()]);
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->jewrineMedina = User::factory()->create(['name' => 'Jewrine Médina']);
    $this->medina = celluleDirigeePar($this->jewrineMedina, ['nom' => 'Médina']);
    $this->jewrineSam = User::factory()->create(['name' => 'Jewrine Sam']);
    $this->sam = celluleDirigeePar($this->jewrineSam, ['nom' => 'Sam']);

    $this->awa = membreR2b('Awa', $this->medina);
    $this->cuisine = Competence::query()->where('nom', 'Cuisine')->firstOrFail();
    $this->sono = Competence::query()->where('nom', 'Sonorisation')->firstOrFail();
});

describe('les savoir-faire', function () {
    it('laisse le membre indiquer ses savoir-faire depuis son profil, parmi la liste en activité', function () {
        $ancien = Competence::query()->create(['nom' => 'Ancien savoir-faire']);
        $ancien->forceFill(['archive_le' => now()])->save();

        $this->actingAs($this->awa->user)->get(route('espace.profil'))->assertOk()->assertSee('Mes savoir-faire')->assertSee('Cuisine')->assertDontSee('Ancien savoir-faire');
        $this->actingAs($this->awa->user)->put(route('espace.profil.update'), [
            'adresse' => 'Médina', 'ville' => 'Kaolack', 'pays_residence' => 'Sénégal', 'competences' => [$this->cuisine->id, $this->sono->id, $ancien->id],
        ])->assertRedirect(route('espace'));

        expect($this->awa->competences()->pluck('nom')->all())->toBe(['Cuisine', 'Sonorisation']);
    });

    it('laisse le Jewrine et le parent les indiquer, jamais un autre membre', function () {
        $service = app(CompetencesService::class);
        $service->definir($this->awa, [$this->cuisine->id], $this->jewrineMedina);
        expect($this->awa->competences()->count())->toBe(1)
            ->and(fn () => $service->definir($this->awa, [], membreR2b('Ibou', $this->sam)->user))->toThrow(DomainException::class);

        $mere = app(FamilleService::class)->ajouterProche($this->awa, [
            'prenom' => 'Mame', 'nom' => 'Fall', 'sexe' => 'F', 'date_naissance' => '1950-01-01', 'lien_famille' => 'parent',
        ], UploadedFile::fake()->image('m.jpg'));
        app(StatutMembreService::class)->changer($mere, StatutMembre::Valide, $this->jewrineMedina);

        $this->actingAs($this->awa->user)->get(route('famille.show', $mere))->assertSee('Ses savoir-faire');
        $this->actingAs($this->awa->user)->post(route('famille.competences', $mere), ['competences' => [$this->cuisine->id]])->assertRedirect(route('famille.show', $mere));
        expect($mere->competences()->pluck('nom')->all())->toBe(['Cuisine']);
    });

    it('fait tenir la liste des savoir-faire par le super administrateur, qui archive au lieu de supprimer', function () {
        $this->actingAs($this->super);
        expect(CompetenceResource::canViewAny())->toBeTrue()->and(CompetenceResource::canDelete($this->cuisine))->toBeFalse();
        $this->actingAs(nommerPourLeTest(membreR2b('Serigne', $this->sam)->user, 'jewrine_national'));
        expect(CompetenceResource::canViewAny())->toBeFalse();
    });
});

describe('le volontariat', function () {
    beforeEach(function () {
        $this->chef = membreR2b('Modou', $this->sam);
        $this->cdgmt = Instance::query()->create(['type' => Instance::COMITE_ORGANISATION, 'nom' => 'CDGMT 2026', 'code' => 'CDGMT', 'annee' => 2026]);
        $this->commission = CommissionComite::query()->create(['instance_id' => $this->cdgmt->id, 'nom' => 'Cuisine', 'chef_id' => $this->chef->id]);
    });

    it('laisse un membre se proposer, et le chef l\'accueillir ou non', function () {
        Notification::fake();

        $this->actingAs($this->awa->user)->get(route('volontariat.index'))->assertOk()->assertSee('CDGMT 2026')->assertSee('Je me propose');
        $this->actingAs($this->awa->user)->post(route('volontariat.proposer', $this->commission), ['message' => 'Je cuisine pour 200 personnes'])->assertSessionHas('succes');
        $this->actingAs($this->awa->user)->post(route('volontariat.proposer', $this->commission))->assertSessionHas('volontariat_erreur');
        Notification::assertSentTo($this->chef->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->toArray($this->chef->user)['message'], 'Je cuisine pour 200 personnes'));

        $proposition = Volontariat::query()->where('membre_id', $this->awa->id)->firstOrFail();
        $this->actingAs($this->chef->user)->get(route('comites.index'))->assertSee('Volontaires à accueillir (1)')->assertSee('Awa');
        $this->actingAs(membreR2b('Ibou', $this->sam)->user)->post(route('comites.volontaires.decider', $proposition), ['accepte' => 1])->assertSessionHas('comite_erreur');
        $this->actingAs($this->chef->user)->post(route('comites.volontaires.decider', $proposition), ['accepte' => 1])->assertSessionHas('comite_succes');

        expect($proposition->fresh()->statut)->toBe(Volontariat::ACCEPTE)
            ->and($this->commission->membres()->whereKey($this->awa->id)->exists())->toBeTrue()
            ->and($this->cdgmt->membres()->whereKey($this->awa->id)->exists())->toBeTrue();
        Notification::assertSentTo($this->awa->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->toArray($this->awa->user)['message'], 'acceptée'));
        $this->actingAs($this->awa->user)->get(route('volontariat.index'))->assertSee('Vous en faites partie');

        // Un autre volontaire n'est pas retenu, avec un motif ; un troisième retire sa proposition.
        $fatou = membreR2b('Fatou', $this->medina);
        $this->actingAs($fatou->user)->post(route('volontariat.proposer', $this->commission));
        $this->actingAs($this->chef->user)->post(route('comites.volontaires.decider', Volontariat::query()->where('membre_id', $fatou->id)->firstOrFail()), ['accepte' => 0, 'motif' => 'Équipe complète']);
        expect(Volontariat::query()->where('membre_id', $fatou->id)->value('motif_refus'))->toBe('Équipe complète');

        $ibra = membreR2b('Ibra', $this->medina);
        $this->actingAs($ibra->user)->post(route('volontariat.proposer', $this->commission));
        $sienne = Volontariat::query()->where('membre_id', $ibra->id)->firstOrFail();
        $this->actingAs($ibra->user)->post(route('volontariat.retirer', $sienne))->assertSessionHas('succes');
        expect($sienne->fresh()->statut)->toBe(Volontariat::RETIRE);
    });

    it('laisse le chef trouver des membres par savoir-faire dans toute la Daara, et les inviter', function () {
        Notification::fake();
        app(CompetencesService::class)->definir($this->awa, [$this->cuisine->id], $this->awa->user);

        $this->actingAs($this->chef->user)->get(route('comites.index', ['commission' => $this->commission->id, 'competence' => $this->cuisine->id]))
            ->assertOk()->assertSee('Trouver des membres par savoir-faire')->assertSee('Awa')->assertSee('Médina')
            ->assertDontSee($this->awa->telephone);

        $this->actingAs($this->chef->user)->post(route('comites.volontaires.inviter', [$this->commission, $this->awa]))->assertSessionHas('comite_succes');
        $invitation = Volontariat::query()->where('membre_id', $this->awa->id)->firstOrFail();
        expect($invitation->statut)->toBe(Volontariat::INVITE);

        $this->actingAs($this->awa->user)->get(route('volontariat.index'))->assertSee('Invitation : commission Cuisine');
        $this->actingAs($this->awa->user)->post(route('volontariat.repondre', $invitation), ['accepte' => 1])->assertSessionHas('succes');

        expect($invitation->fresh()->statut)->toBe(Volontariat::ACCEPTE)->and($this->commission->membres()->whereKey($this->awa->id)->exists())->toBeTrue();
        Notification::assertSentTo($this->chef->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->toArray($this->chef->user)['message'], 'accepte de rejoindre'));

        // Un membre ordinaire ne cherche pas, et le Comité directeur n'accueille pas de volontaires.
        expect(app(CompetencesService::class)->peutRechercher(membreR2b('Ibou', $this->sam)->user))->toBeFalse();
        $commissionCd = CommissionComite::query()->create(['instance_id' => Instance::comiteDirecteur()->id, 'nom' => 'Bureau', 'chef_id' => $this->chef->id]);
        $this->actingAs($this->awa->user)->post(route('volontariat.proposer', $commissionCd))->assertSessionHas('volontariat_erreur', 'Cette commission n\'accueille pas de volontaires.');
    });
});

describe('le changement de cellule', function () {
    it('laisse le membre demander, le super administrateur valider : ses proches le suivent, ses versements passés restent à l\'ancienne cellule', function () {
        Notification::fake();
        $enfant = app(FamilleService::class)->ajouterProche($this->awa, [
            'prenom' => 'Fallou', 'nom' => 'Fall', 'sexe' => 'H', 'date_naissance' => now()->subYears(8)->toDateString(), 'lien_famille' => 'enfant',
        ], UploadedFile::fake()->image('f.jpg'));
        $collecte = Collecte::query()->create([
            'titre' => 'Magal 2026', 'type_programme_id' => typeProgramme('magal'), 'mode' => ModeCollecte::Jayante, 'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte,
        ]);
        $tresorier = User::factory()->create();
        $tresorier->assignRole('jewrine_finance');
        app(CollecteService::class)->fixerParMembre($collecte, $this->awa, 10000);
        app(PaiementService::class)->confirmer(app(CollecteService::class)->declarer($collecte, $this->awa, 4000, 'T_AWA0000000001A'), $tresorier);

        $this->actingAs($this->awa->user)->get(route('cellule.index'))->assertOk()->assertSee('Demander à changer de cellule');
        $this->actingAs($this->awa->user)->post(route('cellule.demander'), ['cellule_id' => $this->sam->id, 'motif' => 'Déménagement'])->assertRedirect(route('cellule.index'));
        $this->actingAs($this->awa->user)->post(route('cellule.demander'), ['cellule_id' => $this->sam->id])->assertSessionHasErrors('cellule_id');
        Notification::assertSentTo($this->super, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->super)['titre'] === 'Changement de cellule à valider');

        $demande = ChangementCellule::query()->firstOrFail();
        expect(fn () => app(ChangementCelluleService::class)->valider($demande, $this->jewrineMedina))->toThrow(DomainException::class, 'Seul le super administrateur')
            ->and(app(ChangementCelluleService::class)->avertissement($demande))->toContain('Fallou')->not->toContain('ATTENTION');

        $this->actingAs($this->super);
        Livewire::test(ListChangementsCellule::class)
            ->assertSee('Awa')
            ->callAction(TestAction::make('valider')->table($demande))
            ->assertHasNoActionErrors();

        expect($demande->fresh()->statut)->toBe(ChangementCellule::VALIDE)
            ->and($this->awa->fresh()->cellule_id)->toBe($this->sam->id)
            ->and($enfant->fresh()->cellule_id)->toBe($this->sam->id)
            ->and(HistoriqueCellule::query()->where('membre_id', $this->awa->id)->whereNotNull('fin_le')->value('cellule_id'))->toBe($this->medina->id)
            ->and(HistoriqueCellule::query()->where('membre_id', $this->awa->id)->whereNull('fin_le')->value('changement_id'))->toBe($demande->id);
        Notification::assertSentTo($this->jewrineMedina, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->toArray($this->jewrineMedina)['message'], 'Médina → Sam'));
        Notification::assertSentTo($this->jewrineSam, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->toArray($this->jewrineSam)['message'], 'avec Fallou'));

        // Le versement d'avant reste à Médina ; le suivant compte pour Sam.
        app(PaiementService::class)->confirmer(app(CollecteService::class)->declarer($collecte, $this->awa->fresh(), 3000, 'T_AWA0000000002B'), $tresorier);
        expect(Paiement::query()->where('reference', 'T_AWA0000000001A')->value('cellule_id'))->toBe($this->medina->id)
            ->and(app(CollecteService::class)->versesParCellule($collecte))->toBe(['Médina' => 4000, 'Sam' => 3000]);

        $this->actingAs($this->awa->user)->get(route('cellule.index'))->assertSee('Mes cellules')->assertSee('Médina')->assertSee('Validé');

        $this->actingAs($this->super);
        Livewire::test(HistoriqueCellulesDuMembre::class, ['record' => $this->awa->fresh()])
            ->assertSee('Historique des cellules')->assertSee('Médina')->assertSee('Sam')->assertSee('En cours')
            ->assertSee('Inscription ou rattachement à la famille')->assertSee('Déménagement');
    });

    it('avertit avant de valider le départ d\'un Jewrine, met fin à ses postes et prévient le Jewrine national', function () {
        Notification::fake();
        $national = nommerPourLeTest(membreR2b('Serigne', $this->sam)->user, 'jewrine_national');
        $jewrine = membreR2b('Cheikh', $this->medina);
        $compte = nommerPourLeTest($jewrine->user, 'jewrine_cellule', $this->medina);
        CellulePointeur::query()->create(['cellule_id' => $this->medina->id, 'user_id' => $compte->id, 'perimetre' => 'hommes']);

        $this->actingAs($this->super);
        Livewire::test(EditMembre::class, ['record' => $jewrine->getRouteKey()])
            ->assertFormFieldDisabled('cellule_id')
            ->callAction('changerCellule', ['cellule_id' => $this->sam->id, 'motif' => 'Mutation'])
            ->assertHasNoActionErrors();

        $demande = ChangementCellule::query()->where('membre_id', $jewrine->id)->firstOrFail();
        expect(app(ChangementCelluleService::class)->avertissement($demande))->toContain('ATTENTION')->toContain('Jewrine de la cellule Médina')->toContain('Pointeur');

        app(ChangementCelluleService::class)->valider($demande, $this->super);

        expect(Nomination::query()->where('user_id', $compte->id)->where('cellule_id', $this->medina->id)->exists())->toBeFalse()
            ->and(CellulePointeur::query()->where('user_id', $compte->id)->exists())->toBeFalse()
            ->and($compte->fresh()->hasRole('jewrine_cellule'))->toBeFalse();
        Notification::assertSentTo($national, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($national)['titre'] === 'Cellule Médina sans Jewrine');
    });

    it('laisse le super administrateur refuser avec un motif, et le demandeur retirer sa demande', function () {
        Notification::fake();
        $service = app(ChangementCelluleService::class);
        $demande = $service->demander($this->awa, $this->sam, null, $this->jewrineMedina);
        Notification::assertSentTo($this->awa->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->awa->user)['titre'] === 'Changement de cellule demandé');

        $this->actingAs($this->jewrineMedina);
        Livewire::test(ListChangementsCellule::class)->assertSee('Awa')->assertActionHidden(TestAction::make('valider')->table($demande));

        expect(fn () => $service->refuser($demande, ' ', $this->super))->toThrow(DomainException::class, 'motif');
        $service->refuser($demande, 'Cellule d\'arrivée en cours de réorganisation', $this->super);
        expect($demande->fresh()->statut)->toBe(ChangementCellule::REFUSE)->and($this->awa->fresh()->cellule_id)->toBe($this->medina->id);

        $nouvelle = $service->demander($this->awa, $this->sam, null, $this->awa->user);
        $this->actingAs($this->awa->user)->post(route('cellule.retirer', $nouvelle))->assertRedirect(route('cellule.index'));
        expect($nouvelle->fresh()->statut)->toBe(ChangementCellule::RETIRE)
            ->and(fn () => $service->demander($this->awa, $this->medina, null, $this->awa->user))->toThrow(DomainException::class, 'autre cellule')
            ->and(fn () => $service->demander($this->awa, $this->sam, null, membreR2b('Ibou', $this->sam)->user))->toThrow(DomainException::class, 'demandé par le membre');
    });
});
