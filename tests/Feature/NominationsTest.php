<?php

use App\Enums\StatutMembre;
use App\Filament\Pages\MatriceDesDroits;
use App\Filament\Resources\ComptesBureau\Pages\ManageComptesBureau;
use App\Filament\Resources\Membres\Widgets\CasquettesDuMembre;
use App\Filament\Resources\Nominations\Pages\ManageNominations;
use App\Models\Cellule;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\Nomination;
use App\Models\Poste;
use App\Models\User;
use App\Services\CompteService;
use App\Services\NominationService;
use App\Services\StatutMembreService;
use App\Support\Capacites;
use Database\Seeders\RolesSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');

    $this->service = app(NominationService::class);
});

function membreValideAvecCompte(string $telephone = '+221762223344', string $motDePasse = 'monmotdepasse'): Membre
{
    $user = User::factory()->create(['telephone' => $telephone, 'password' => $motDePasse]);
    $user->assignRole('membre');

    return Membre::factory()->for($user)->valide()->create();
}

function poste(string $code): Poste
{
    return Poste::query()->where('code', $code)->firstOrFail();
}

describe('les nominations', function () {
    it('nomme un membre validé Jewrine finance national, sans toucher à son mot de passe', function () {
        $membre = membreValideAvecCompte();

        $nomination = $this->service->nommer($membre->user, poste('jewrine_finance'), null, $this->super);

        expect($membre->user->fresh()->hasAllRoles(['membre', 'jewrine_finance']))->toBeTrue()
            ->and($membre->user->fresh()->can('paiements.valider'))->toBeTrue()
            ->and($nomination->designe_par)->toBe($this->super->id)
            ->and(Hash::check('monmotdepasse', $membre->user->fresh()->password))->toBeTrue();
    });

    it('refuse de nommer un membre qui n\'est pas encore validé', function () {
        $user = User::factory()->create();
        Membre::factory()->for($user)->create();

        $this->service->nommer($user, poste('jewrine_finance'), null, $this->super);
    })->throws(DomainException::class, 'membre validé');

    it('exige la cellule d\'un Jewrine de cellule et lui en donne les droits, pour elle seule', function () {
        $kaolack = Cellule::factory()->create();
        $dakar = Cellule::factory()->create();
        $membre = membreValideAvecCompte();

        expect(fn () => $this->service->nommer($membre->user, poste('jewrine_cellule'), null, $this->super))->toThrow(DomainException::class, 'cellule');

        $this->service->nommer($membre->user, poste('jewrine_cellule'), $kaolack, $this->super);
        $jewrine = $membre->user->fresh();

        expect($kaolack->jewrine()?->id)->toBe($jewrine->id)
            ->and($jewrine->can('view', Membre::factory()->for($kaolack)->create()))->toBeTrue()
            ->and($jewrine->can('view', Membre::factory()->for($dakar)->create()))->toBeFalse();
    });

    it('laisse le Jewrine national nommer, mais seul le super administrateur nomme le Jewrine national', function () {
        $national = nommerPourLeTest(membreValideAvecCompte('+221760000001')->user, 'jewrine_national');
        $secretaire = membreValideAvecCompte('+221760000002');
        $successeur = membreValideAvecCompte('+221760000003');

        $this->service->nommer($secretaire->user, poste('secretaire_general'), null, $national);

        expect($secretaire->user->fresh()->hasRole('secretaire_general'))->toBeTrue()
            ->and(fn () => $this->service->nommer($successeur->user, poste('jewrine_national'), null, $national))
            ->toThrow(DomainException::class, 'Seul le super administrateur nomme le Jewrine national')
            ->and(fn () => $this->service->nommer($successeur->user, poste('jewrine_finance'), null, $secretaire->user->fresh()))
            ->toThrow(DomainException::class);
    });

    it('remplace le titulaire d\'un poste unique, en gardant l\'historique', function () {
        $ancien = membreValideAvecCompte('+221760000004');
        $nouveau = membreValideAvecCompte('+221760000005');

        $premiere = $this->service->nommer($ancien->user, poste('secretaire_general'), null, $this->super);
        $this->service->nommer($nouveau->user, poste('secretaire_general'), null, $this->super);

        $premiere = Nomination::query()->withoutGlobalScope(Nomination::EN_COURS)->findOrFail($premiere->id);

        expect($premiere->estTerminee())->toBeTrue()
            ->and($premiere->motif_fin)->toContain('Remplacé(e) par')
            ->and($ancien->user->fresh()->hasRole('secretaire_general'))->toBeFalse()
            ->and($nouveau->user->fresh()->hasRole('secretaire_general'))->toBeTrue()
            ->and(fn () => $premiere->delete())->toThrow(DomainException::class);
    });

    it('additionne les casquettes, et n\'en retire qu\'une quand une nomination prend fin', function () {
        $kaolack = Cellule::factory()->create();
        $membre = membreValideAvecCompte();

        $this->service->nommer($membre->user, poste('jewrine_cellule'), $kaolack, $this->super);
        $finance = $this->service->nommer($membre->user, poste('jewrine_finance'), null, $this->super);
        $personne = $membre->user->fresh();

        expect($personne->can('paiements.valider'))->toBeTrue()
            ->and($personne->can('dahiras.gerer.cellule'))->toBeTrue();

        $this->service->mettreFin($finance, 'Fin de mandat', $this->super);
        $personne = $membre->user->fresh();

        expect($personne->can('paiements.valider'))->toBeFalse()
            ->and($personne->can('dahiras.gerer.cellule'))->toBeTrue()
            ->and(fn () => $this->service->mettreFin($finance->fresh() ?? $finance, '', $this->super))->toThrow(DomainException::class);
    });

    it('met fin à toutes les nominations d\'un membre radié', function () {
        $membre = membreValideAvecCompte();
        $this->service->nommer($membre->user, poste('jewrine_finance'), null, $this->super);

        app(StatutMembreService::class)->changer($membre, StatutMembre::Radie, $this->super, 'A quitté la Daara');

        expect($membre->user->fresh()->hasRole('jewrine_finance'))->toBeFalse()
            ->and(Nomination::query()->withoutGlobalScope(Nomination::EN_COURS)->where('user_id', $membre->user_id)->value('motif_fin'))->toBe('Membre radié');
    });
});

describe('les écrans', function () {
    it('nomme et met fin depuis l\'écran des nominations', function () {
        $membre = membreValideAvecCompte();
        $this->actingAs($this->super);

        Livewire::test(ManageNominations::class)
            ->callAction('nommer', data: ['user_id' => $membre->user_id, 'poste_id' => poste('jewrine_finance')->id, 'debut_le' => now()->toDateString()])
            ->assertHasNoActionErrors();

        $nomination = Nomination::query()->where('user_id', $membre->user_id)->firstOrFail();

        Livewire::test(ManageNominations::class)
            ->assertSee($membre->user->name)
            ->callAction(TestAction::make('mettreFin')->table($nomination), data: ['motif' => 'Démission']);

        expect($nomination->fresh()?->estTerminee())->toBeTrue();

        Livewire::test(CasquettesDuMembre::class, ['record' => $membre])->assertSee('Jewrine finance national')->assertSee('Démission');
    });

    it('réserve les nominations au super administrateur et au Jewrine national', function () {
        $secretaire = nommerPourLeTest(membreValideAvecCompte()->user, 'secretaire_general');
        $national = nommerPourLeTest(membreValideAvecCompte('+221760000006')->user, 'jewrine_national');

        $this->actingAs($secretaire)->get('/admin/nominations')->assertForbidden();
        $this->flushSession();
        $this->actingAs($national)->get('/admin/nominations')->assertOk();
    });

    it('réserve les comptes, la matrice et les réglages au super administrateur', function () {
        $admin = User::factory()->create();
        $admin->assignRole('secretaire_general');

        foreach (['/admin/comptes', '/admin/parametres', '/admin/matrice-des-droits'] as $adresse) {
            $this->actingAs($this->super)->get($adresse)->assertOk();
            $this->actingAs($admin)->get($adresse)->assertForbidden();
        }

        $this->actingAs($this->super);
        Livewire::test(MatriceDesDroits::class)->assertSee('Jewrine de cellule')->assertSee('membres.voir');
    });

    it('désactive un compte depuis l\'écran des comptes : il ne peut plus se connecter', function () {
        $user = nommerPourLeTest(membreValideAvecCompte()->user, 'jewrine_finance');
        $this->actingAs($this->super);

        Livewire::test(ManageComptesBureau::class)->callAction(TestAction::make('changerEtat')->table($user));
        auth()->logout();

        expect($user->fresh()->actif)->toBeFalse();
        $this->post('/connexion', ['telephone' => '762223344', 'password' => 'monmotdepasse'])->assertSessionHasErrors('telephone');
    });

    it('génère un nouveau mot de passe, et protège le compte du super administrateur', function () {
        $user = nommerPourLeTest(membreValideAvecCompte()->user, 'jewrine_finance');

        $nouveau = app(CompteService::class)->reinitialiserMotDePasse($user);

        expect(Hash::check($nouveau, $user->fresh()->password))->toBeTrue()
            ->and(fn () => app(CompteService::class)->changerEtat($this->super, false))->toThrow(DomainException::class);
    });

    it('montre « Panneau du bureau » sur le site aux seuls responsables', function () {
        $membre = membreValideAvecCompte();
        $simple = membreValideAvecCompte('+221769998877');
        nommerPourLeTest($membre->user, 'jewrine_finance');

        $this->actingAs($membre->user)->get('/mon-espace')->assertSee('Panneau du bureau')->assertSee('Jewrine finance national');
        $this->actingAs($simple->user)->get('/mon-espace')->assertDontSee('Panneau du bureau');
    });

    it('montre « Mon compte de membre » dans le panneau', function () {
        $membre = membreValideAvecCompte();
        nommerPourLeTest($membre->user, 'secretaire_general');

        $this->actingAs($membre->user)->get('/admin')->assertOk()->assertSee('Mon compte de membre');
    });
});

describe('la matrice et les super administrateurs', function () {
    it('donne toutes les capacités au super administrateur, et plus aucun rôle « administrateur »', function () {
        expect(Role::query()->where('name', 'administrateur')->exists())->toBeFalse()
            ->and($this->super->can('membres.voir'))->toBeTrue()
            ->and($this->super->can('paiements.secours'))->toBeTrue()
            ->and($this->super->can('caisse.tenir'))->toBeFalse()
            ->and(Capacites::portee('jewrine_cellule', 'membres.voir'))->toBe('Cellule')
            ->and(Capacites::portee('jewrine_finance', 'membres.voir'))->toBe('Daara')
            ->and(Capacites::portee('membre', 'membres.voir'))->toBeNull();
    });

    it('accepte deux super administrateurs au plus, et jamais aucun', function () {
        $second = User::factory()->create(['telephone' => '+221761111111']);
        $troisieme = User::factory()->create(['telephone' => '+221762222222']);

        $this->artisan('ndk:super-admin', ['telephone' => '761111111'])->assertSuccessful();
        $this->artisan('ndk:super-admin', ['telephone' => '762222222'])->assertFailed();
        $this->artisan('ndk:super-admin')->assertSuccessful();

        expect($second->fresh()->hasRole('super_administrateur'))->toBeTrue()
            ->and($troisieme->fresh()->hasRole('super_administrateur'))->toBeFalse()
            ->and(JournalActivite::query()->where('sujet_id', $second->id)->exists())->toBeTrue();

        $this->artisan('ndk:super-admin', ['telephone' => '761111111', '--retirer' => true])->assertSuccessful();
        $this->artisan('ndk:super-admin', ['telephone' => $this->super->telephone, '--retirer' => true])->assertFailed();
        $this->artisan('ndk:super-admin', ['telephone' => '700000000'])->assertFailed();

        expect($this->super->fresh()->hasRole('super_administrateur'))->toBeTrue();
    });

    it('reporte la matrice dans la base', function () {
        $this->artisan('droits:synchroniser')->assertSuccessful();

        expect(Role::findByName('jewrine_finance')->hasPermissionTo('paiements.valider'))->toBeTrue()
            ->and(Role::findByName('secretaire_general')->hasPermissionTo('paiements.valider'))->toBeFalse();
    });
});
