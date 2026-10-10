<?php

use App\Enums\StatutCarte;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Filament\Exports\MembreExporter;
use App\Filament\Exports\PaiementExporter;
use App\Filament\Imports\MembreImporter;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Cellules\Pages\ManageCellules;
use App\Filament\Resources\Commissions\Pages\ManageCommissions;
use App\Filament\Resources\ComptesBureau\Pages\ManageComptesBureau;
use App\Filament\Resources\Exercices\Pages\ManageExercices;
use App\Filament\Resources\Membres\Pages\EditMembre;
use App\Filament\Resources\Membres\Pages\ListMembres;
use App\Filament\Resources\Paiements\Pages\ManagePaiements;
use App\Filament\Resources\Parametres\Pages\ManageParametres;
use App\Filament\Resources\Postes\Pages\ManagePostes;
use App\Filament\Resources\Tarifs\Pages\ManageTarifs;
use App\Models\Carte;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\Parametre;
use App\Models\User;
use App\Services\CarteService;
use App\Services\CotisationService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/*
 * Les boutons du panneau, testés comme si le bureau cliquait dessus.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole(['super_administrateur', 'administrateur']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(['secretaire_general', 'administrateur']);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('tresorier');
});

function membreAvecCompte(string $etat = 'valide'): Membre
{
    $compte = User::factory()->create();
    $compte->assignRole('membre');

    $fabrique = Membre::factory()->for($compte);

    return match ($etat) {
        'valide' => $fabrique->valide()->create(['valide_le' => now()->startOfMonth()->subMonths(3)]),
        'accepte' => $fabrique->accepte()->create(),
        default => $fabrique->create(['statut' => StatutMembre::EnAttente]),
    };
}

describe('les paiements', function () {
    it('confirme une déclaration de cotisation', function () {
        $paiement = app(CotisationService::class)->declarer(membreAvecCompte(), 'TPANNEAU1', 2);

        $this->actingAs($this->tresorier);

        Livewire::test(ManagePaiements::class)
            ->callAction(TestAction::make('confirmer')->table($paiement))
            ->assertNotified('Cotisation confirmée.');

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Confirme);
    });

    it('refuse une déclaration avec un motif', function () {
        $paiement = app(CotisationService::class)->declarer(membreAvecCompte(), 'TPANNEAU2', 1);

        $this->actingAs($this->tresorier);

        Livewire::test(ManagePaiements::class)
            ->callAction(TestAction::make('refuser')->table($paiement), data: ['motif' => 'Référence introuvable'])
            ->assertHasNoActionErrors();

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Refuse)
            ->and($paiement->fresh()->motif_refus)->toBe('Référence introuvable');
    });

    it('exige un motif pour refuser', function () {
        $paiement = app(CotisationService::class)->declarer(membreAvecCompte(), 'TPANNEAU3', 1);

        $this->actingAs($this->tresorier);

        Livewire::test(ManagePaiements::class)
            ->callAction(TestAction::make('refuser')->table($paiement), data: ['motif' => ''])
            ->assertHasActionErrors(['motif' => 'required']);

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Declare);
    });

    it('empêche le Jewrine finance de confirmer son propre paiement depuis le panneau', function () {
        $saFiche = Membre::factory()->for($this->tresorier)->valide()->create(['valide_le' => now()->subMonths(2)]);
        $paiement = app(CotisationService::class)->declarer($saFiche, 'TPANNEAU4', 1);

        $this->actingAs($this->tresorier);

        Livewire::test(ManagePaiements::class)
            ->callAction(TestAction::make('confirmer')->table($paiement))
            ->assertNotified('Vous ne pouvez pas valider votre propre paiement : demandez à un autre membre du bureau.');

        expect($paiement->fresh()->statut)->toBe(StatutPaiement::Declare);
    });
});

describe('les membres', function () {
    it('enregistre des frais de carte en espèces', function () {
        $membre = membreAvecCompte('accepte');

        $this->actingAs($this->tresorier);

        Livewire::test(ListMembres::class)
            ->callAction(TestAction::make('paiementEspeces')->table($membre))
            ->assertHasNoActionErrors();

        expect($membre->fresh()->statut)->toBe(StatutMembre::Valide);
    });

    it('enregistre une cotisation en espèces', function () {
        $membre = membreAvecCompte();

        $this->actingAs($this->tresorier);

        Livewire::test(ListMembres::class)
            ->callAction(TestAction::make('cotisationEspeces')->table($membre), data: ['nombre_mois' => 2])
            ->assertHasNoActionErrors();

        expect(Cotisation::query()->where('membre_id', $membre->id)->count())->toBe(2);
    });

    it('réserve la dispense au super administrateur', function () {
        $membre = membreAvecCompte();

        $this->actingAs($this->tresorier);
        Livewire::test(ListMembres::class)
            ->assertActionHidden(TestAction::make('exonerer')->table($membre));

        $this->actingAs($this->super);
        Livewire::test(ListMembres::class)
            ->callAction(TestAction::make('exonerer')->table($membre), data: ['nombre_mois' => 1, 'motif' => 'Maladie'])
            ->assertHasNoActionErrors();

        expect(Cotisation::query()->where('membre_id', $membre->id)->where('exoneree', true)->count())->toBe(1);
    });

    it('accepte puis rejette des demandes', function () {
        $aAccepter = membreAvecCompte('en_attente');
        $aRejeter = membreAvecCompte('en_attente');

        $this->actingAs($this->admin);

        Livewire::test(ListMembres::class)
            ->callAction(TestAction::make('statut_accepte')->table($aAccepter))
            ->callAction(TestAction::make('statut_rejete')->table($aRejeter), data: ['motif' => 'Doublon'])
            ->assertHasNoActionErrors();

        expect($aAccepter->fresh()->statut)->toBe(StatutMembre::Accepte)
            ->and($aRejeter->fresh()->statut)->toBe(StatutMembre::Rejete);
    });

    it('donne un mot de passe provisoire à un membre', function () {
        $membre = membreAvecCompte();

        $this->actingAs($this->admin);

        Livewire::test(ListMembres::class)
            ->callAction(TestAction::make('nouveauMotDePasse')->table($membre))
            ->assertHasNoActionErrors();

        expect((bool) $membre->user->fresh()->getAttribute('doit_changer_mot_de_passe'))->toBeTrue();
    });

    it('révoque une carte et en émet une nouvelle', function () {
        $membre = membreAvecCompte();
        app(CarteService::class)->emettre($membre);

        $this->actingAs($this->admin);

        Livewire::test(EditMembre::class, ['record' => $membre->getRouteKey()])
            ->callAction('revoquerCarte', data: ['motif' => 'Carte perdue'])
            ->assertHasNoActionErrors();

        expect(Carte::query()->where('membre_id', $membre->id)->count())->toBe(2)
            ->and(Carte::query()->where('membre_id', $membre->id)->where('statut', 'active')->count())->toBe(1);
    });
});

describe('les comptes du bureau', function () {
    it('nomme un membre validé au bureau', function () {
        $membre = membreAvecCompte();

        $this->actingAs($this->super);

        Livewire::test(ManageComptesBureau::class)
            ->callAction('nommer', data: ['membre_id' => $membre->id, 'role' => 'tresorier'])
            ->assertHasNoActionErrors();

        expect($membre->user->fresh()->hasRole('tresorier'))->toBeTrue();
    });

    it('retire un membre du bureau et lui donne un nouveau mot de passe', function () {
        $this->actingAs($this->super);

        Livewire::test(ManageComptesBureau::class)
            ->callAction(TestAction::make('reinitialiserMotDePasse')->table($this->tresorier))
            ->callAction(TestAction::make('retirerDuBureau')->table($this->tresorier))
            ->assertHasNoActionErrors();

        $compte = $this->tresorier->fresh();

        expect($compte->hasRole('tresorier'))->toBeFalse()
            ->and((bool) $compte->getAttribute('doit_changer_mot_de_passe'))->toBeTrue();
    });
});

describe('les réglages', function () {
    it('active le mode maintenance', function () {
        $this->actingAs($this->super);

        Livewire::test(ManageParametres::class)->callAction('maintenance');

        expect(Parametre::valeur('maintenance'))->toBe('1');
    });

    it('refuse un faux lien Wave et accepte un vrai', function () {
        $reglage = Parametre::query()->where('cle', 'wave_lien_1_mois')->firstOrFail();

        $this->actingAs($this->super);

        Livewire::test(ManageParametres::class)
            ->callAction(TestAction::make('edit')->table($reglage), data: ['valeur' => 'https://faux-wave.com/payer'])
            ->assertHasActionErrors(['valeur']);

        Livewire::test(ManageParametres::class)
            ->callAction(TestAction::make('edit')->table($reglage), data: ['valeur' => 'https://pay.wave.com/m/UNMOIS'])
            ->assertHasNoActionErrors();

        expect(Parametre::valeur('wave_lien_1_mois'))->toBe('https://pay.wave.com/m/UNMOIS');
    });
});

it('ouvre le formulaire de création de chaque donnée de référence', function (string $page) {
    $this->actingAs($this->admin);

    Livewire::test($page)
        ->mountAction('create')
        ->assertActionMounted('create');
})->with([
    'cellules' => ManageCellules::class,
    'commissions' => ManageCommissions::class,
    'exercices' => ManageExercices::class,
    'postes' => ManagePostes::class,
    'tarifs' => ManageTarifs::class,
]);

it('connecte un administrateur au panneau avec son numéro de téléphone', function () {
    $compte = User::factory()->create(['telephone' => '+221770001122', 'password' => 'motdepasse1']);
    $compte->assignRole(['secretaire_general', 'administrateur']);

    $this->get('/admin/login')->assertOk();

    Livewire::test(Login::class)
        ->fillForm(['telephone' => '77 000 11 22', 'password' => 'motdepasse1'])
        ->call('authenticate');

    $this->assertAuthenticatedAs($compte);
});

it('décrit les colonnes et les messages des exports et de l\'import', function () {
    $export = new Export;
    $export->forceFill(['successful_rows' => 3, 'total_rows' => 3]);

    $import = new Import;
    $import->forceFill(['successful_rows' => 2, 'total_rows' => 2]);

    expect(PaiementExporter::getColumns())->not->toBeEmpty()
        ->and(MembreExporter::getColumns())->not->toBeEmpty()
        ->and(MembreImporter::getColumns())->not->toBeEmpty()
        ->and(PaiementExporter::getCompletedNotificationBody($export))->toContain('3')
        ->and(MembreExporter::getCompletedNotificationBody($export))->toContain('3')
        ->and(MembreImporter::getCompletedNotificationBody($import))->toContain('2');
});

it('donne un libellé en français à chaque statut de carte et de paiement', function () {
    foreach ([...StatutCarte::cases(), ...StatutPaiement::cases()] as $statut) {
        expect($statut->getLabel())->not->toBeEmpty();

        if (method_exists($statut, 'getColor')) {
            expect($statut->getColor())->not->toBeEmpty();
        }
    }
});
