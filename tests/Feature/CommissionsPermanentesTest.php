<?php

use App\Enums\StatutMembre;
use App\Filament\Resources\Commissions\Pages\ManageCommissions;
use App\Filament\Resources\Commissions\Widgets\MembresDeLaCommission;
use App\Filament\Resources\Instances\Widgets\MembresInstance;
use App\Models\AppartenanceCommission;
use App\Models\Commission;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\Poste;
use App\Models\Reunion;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\ComiteService;
use App\Services\CommissionService;
use App\Services\NominationService;
use App\Services\StatutMembreService;
use Database\Seeders\CommissionsSeeder;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function membrePermanent(string $prenom, StatutMembre $statut = StatutMembre::Valide): Membre
{
    $compte = User::factory()->create(['name' => $prenom]);
    $compte->assignRole('membre');

    return Membre::factory()->for($compte)->create(['prenom' => $prenom, 'statut' => $statut]);
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, CommissionsSeeder::class]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');

    $this->communication = Commission::query()->where('nom', 'Communication')->firstOrFail();
    $this->suivi = Commission::query()->where('code', Commission::SUIVI_EVALUATION)->firstOrFail();
    $this->jewrine = membrePermanent('Khady');
    $this->compteJewrine = nommerPourLeTest($this->jewrine->user, 'jewrine_commission', null, $this->communication);
    $this->service = app(CommissionService::class);
});

describe('le Jewrine de commission', function () {
    it('est nommé par le super administrateur seul, et ne voit que sa commission', function () {
        $national = nommerPourLeTest(membrePermanent('Serigne')->user, 'jewrine_national');
        $candidat = membrePermanent('Ndeye');
        $poste = Poste::query()->where('code', 'jewrine_commission')->firstOrFail();

        expect(fn () => app(NominationService::class)->nommer($candidat->user, $poste, null, $national, null, $this->communication))
            ->toThrow(DomainException::class, 'Seul le super administrateur')
            ->and(fn () => app(NominationService::class)->nommer($candidat->user, $poste, null, $this->super))
            ->toThrow(DomainException::class, 'commission');

        expect($this->communication->jewrine()?->id)->toBe($this->compteJewrine->id)
            ->and($this->compteJewrine->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();

        $this->actingAs($this->compteJewrine);
        Livewire::test(ManageCommissions::class)->assertSee('Communication')->assertDontSee('Organisation');
        $this->get('/admin/commissions/'.$this->suivi->id)->assertForbidden();
    });

    it('accepte ou refuse les souhaits depuis son téléphone, et le membre est prévenu', function () {
        Notification::fake();
        $awa = membrePermanent('Awa');
        $ali = membrePermanent('Ali');
        $souhaitAwa = $this->service->souhaiter($awa, $this->communication);
        $souhaitAli = $this->service->souhaiter($ali, $this->communication);
        $this->actingAs($this->compteJewrine);

        $this->get(route('comites.index'))->assertOk()->assertSee('Souhaits à traiter (2)');
        $this->post(route('commissions.souhaits.accepter', $souhaitAwa))->assertSessionHas('comite_succes');
        $this->post(route('commissions.souhaits.refuser', $souhaitAli), ['motif' => 'Commission complète'])->assertSessionHas('comite_succes');

        expect($awa->commissions()->pluck('nom')->all())->toBe(['Communication'])
            ->and($souhaitAli->fresh()?->refuse_le)->not->toBeNull()
            ->and($this->service->comite($this->communication)->membres()->whereKey($awa->id)->exists())->toBeTrue();

        Notification::assertSentTo($ali->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->message, 'Commission complète'));
        Notification::assertSentTo($awa->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->message, 'Bienvenue'));
    });

    it('ajoute un membre par son téléphone, le retire avec un motif, et garde l\'historique', function () {
        $fatou = membrePermanent('Fatou');
        $this->actingAs($this->compteJewrine);

        $this->post(route('commissions.membres.ajouter', $this->communication), ['membre' => $fatou->telephone])->assertSessionHas('comite_succes');
        $appartenance = AppartenanceCommission::query()->where('membre_id', $fatou->id)->firstOrFail();
        $this->post(route('commissions.membres.retirer', $appartenance), ['motif' => 'Départ à l\'étranger'])->assertSessionHas('comite_succes');

        expect($fatou->commissions()->count())->toBe(0)
            ->and($appartenance->fresh()?->motif_fin)->toBe('Départ à l\'étranger')
            ->and(fn () => $appartenance->delete())->toThrow(DomainException::class);

        Livewire::test(MembresDeLaCommission::class, ['record' => $this->communication])
            ->filterTable('etat', 'historique')
            ->assertSee('Fatou')->assertSee('Départ à l\'étranger');
    });

    it('refuse qu\'une autre personne gère les membres', function () {
        $autre = nommerPourLeTest(membrePermanent('Modou')->user, 'secretaire_general');

        expect(fn () => $this->service->ajouter($this->communication, membrePermanent('Binta'), $autre))
            ->toThrow(DomainException::class, 'Seul le Jewrine de la commission');
    });

    it('n\'accepte un souhait que pour un membre validé', function () {
        $souhait = $this->service->souhaiter(membrePermanent('Lamine', StatutMembre::EnAttente), $this->communication);

        expect(fn () => $this->service->accepter($souhait, $this->compteJewrine))->toThrow(DomainException::class, 'validé');
    });

    it('gère les membres depuis le panneau', function () {
        $awa = membrePermanent('Awa');
        $souhait = $this->service->souhaiter($awa, $this->communication);
        $this->actingAs($this->compteJewrine);

        Livewire::test(MembresDeLaCommission::class, ['record' => $this->communication])
            ->filterTable('etat', 'souhaits')
            ->callAction(TestAction::make('accepter')->table($souhait));

        expect($awa->commissions()->count())->toBe(1);
    });
});

describe('Suivi et Évaluation', function () {
    it('donne la casquette Suivi et Évaluation à ses membres, et la retire à leur départ', function () {
        $moussa = membrePermanent('Moussa');

        $appartenance = $this->service->ajouter($this->suivi, $moussa, $this->super);
        expect($moussa->user->fresh()->hasRole('suivi_evaluation'))->toBeTrue()
            ->and($moussa->user->fresh()->can('projets.gerer'))->toBeTrue();

        $this->service->retirer($appartenance, 'Fin de mandat', $this->super);
        expect($moussa->user->fresh()->hasRole('suivi_evaluation'))->toBeFalse();
    });

    it('n\'est plus un poste que l\'on nomme', function () {
        expect(Poste::query()->where('code', 'suivi_evaluation')->value('actif'))->toBeFalse();
    });
});

describe('l\'espace de la commission', function () {
    it('réunit les membres dans « Mes comités », et laisse le Jewrine organiser les réunions', function () {
        $awa = membrePermanent('Awa');
        $this->service->ajouter($this->communication, $awa, $this->compteJewrine);
        $espace = $this->service->comite($this->communication);

        $this->actingAs($awa->user)->get(route('comites.index'))->assertOk()->assertSee('Commission Communication');

        expect(ComiteService::peutOrganiser($this->compteJewrine, $espace))->toBeTrue()
            ->and(array_keys(ComiteService::secretairesPossibles($espace)))->toBe([$awa->id])
            ->and(ComiteService::peutOrganiser(nommerPourLeTest(membrePermanent('Modou')->user, 'secretaire_general'), $espace))->toBeFalse();

        $this->actingAs($this->compteJewrine);
        Livewire::test(MembresInstance::class, ['record' => $espace])->assertActionHidden(TestAction::make('ajouter')->table());
        $this->get('/admin/instances/'.$espace->id.'/edit')->assertForbidden();
    });

    it('met fin aux commissions d\'un membre radié', function () {
        $awa = membrePermanent('Awa');
        $this->service->ajouter($this->communication, $awa, $this->super);

        app(StatutMembreService::class)->changer($awa, StatutMembre::Radie, $this->super, 'A quitté la Daara');

        expect($awa->commissions()->count())->toBe(0)
            ->and(AppartenanceCommission::query()->where('membre_id', $awa->id)->value('motif_fin'))->toBe('Membre radié');
    });
});

describe('le secrétaire de séance', function () {
    it('est un membre du Comité directeur pour le Comité directeur et les comités d\'organisation', function () {
        $ousmane = membrePermanent('Ousmane');
        app(ComiteService::class)->definirComiteDirecteur([$ousmane->id], $this->super);
        $cdgmt = Instance::query()->create(['type' => Instance::COMITE_ORGANISATION, 'nom' => 'CDGMT 2026', 'code' => 'CDGMT', 'annee' => 2026, 'president_id' => $ousmane->id]);

        expect(array_keys(ComiteService::secretairesPossibles($cdgmt)))->toBe([$ousmane->id])
            ->and(array_keys(ComiteService::secretairesPossibles(Instance::comiteDirecteur())))->toBe([$ousmane->id]);
    });

    it('rédige seul le PV dès la convocation ; l\'organisateur prépare avant, le super administrateur reste le secours', function () {
        $secretaire = membrePermanent('Aïda');
        $this->service->ajouter($this->communication, $secretaire, $this->super);
        $reunion = Reunion::query()->create([
            'instance_id' => $this->service->comite($this->communication)->id, 'titre' => 'Réunion de rentrée',
            'debut' => now()->addDays(3), 'lieu' => 'Siège', 'secretaire_id' => $secretaire->id,
        ]);

        expect(ComiteService::peutRediger($this->compteJewrine, $reunion))->toBeTrue();

        app(ComiteService::class)->convoquer($reunion);
        $reunion->refresh();

        expect(ComiteService::peutRediger($this->compteJewrine, $reunion))->toBeFalse()
            ->and(ComiteService::peutRediger($secretaire->user, $reunion))->toBeTrue()
            ->and(ComiteService::peutRediger($this->super, $reunion))->toBeTrue()
            ->and(fn () => app(ComiteService::class)->publier($reunion, $this->compteJewrine))->toThrow(DomainException::class, 'secrétaire de séance');
    });
});
