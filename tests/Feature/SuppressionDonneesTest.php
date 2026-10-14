<?php

use App\Enums\StatutMembre;
use App\Filament\Resources\DemandesSuppression\Pages\ManageDemandesSuppression;
use App\Models\Carte;
use App\Models\Commission;
use App\Models\DemandeSuppression;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Services\CarteService;
use App\Services\CotisationService;
use App\Services\DonneesPersonnellesService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');

    $this->user = User::factory()->create(['telephone' => '+221775550011', 'password' => 'motdepasse1']);
    $this->user->assignRole('membre');
    $this->membre = Membre::factory()->for($this->user)->valide()->create([
        'prenom' => 'Awa',
        'nom' => 'Ndiaye',
        'telephone' => '+221775550011',
        'valide_le' => now()->subMonths(2),
    ]);
});

it('enregistre une demande de suppression confirmée par le mot de passe et alerte le super administrateur', function () {
    $this->actingAs($this->user)->post('/mon-compte/suppression', [
        'motif' => 'Je quitte la Daara',
        'mot_de_passe' => 'motdepasse1',
        'confirmation' => '1',
    ])->assertRedirect(route('espace'));

    expect(DemandeSuppression::query()->where('statut', DemandeSuppression::EN_ATTENTE)->count())->toBe(1)
        ->and(DB::table('notifications')->where('notifiable_id', $this->super->id)->count())->toBe(1);
});

it('refuse la demande sans le bon mot de passe', function () {
    $this->actingAs($this->user)->post('/mon-compte/suppression', [
        'mot_de_passe' => 'mauvais1',
        'confirmation' => '1',
    ])->assertSessionHasErrors('mot_de_passe');

    expect(DemandeSuppression::query()->count())->toBe(0);
});

it('n\'accepte qu\'une demande en cours à la fois', function () {
    $donnees = ['mot_de_passe' => 'motdepasse1', 'confirmation' => '1'];

    $this->actingAs($this->user)->post('/mon-compte/suppression', $donnees);
    $this->actingAs($this->user)->post('/mon-compte/suppression', $donnees)->assertSessionHasErrors('confirmation');

    expect(DemandeSuppression::query()->count())->toBe(1);
});

it('efface les données personnelles en gardant la comptabilité', function () {
    $this->membre->commissions()->attach(Commission::factory()->create());
    app(CarteService::class)->emettre($this->membre);
    $paiement = app(CotisationService::class)->declarer($this->membre, 'TSUPPR01', 1, '+221761112233');

    app(DonneesPersonnellesService::class)->anonymiser($this->membre);

    $fiche = $this->membre->fresh();
    $compte = $this->user->fresh();

    expect($fiche->prenom)->toBe('Membre')
        ->and($fiche->nom)->toBe('anonymisé')
        ->and($fiche->telephone)->not->toBe('+221775550011')
        ->and($fiche->statut)->toBe(StatutMembre::Radie)
        ->and($fiche->getAttribute('anonymise_le'))->not->toBeNull()
        ->and($fiche->commissions()->count())->toBe(0)
        ->and($compte->actif)->toBeFalse()
        ->and($compte->name)->toBe('Compte supprimé')
        ->and($compte->roles()->count())->toBe(0)
        ->and(Carte::query()->where('membre_id', $fiche->id)->where('statut', 'active')->count())->toBe(0)
        ->and(Paiement::query()->find($paiement->id)?->montant)->toBe(1000)
        ->and(Paiement::query()->find($paiement->id)?->numero_wave)->toBeNull();
});

it('empêche toute connexion après l\'effacement', function () {
    app(DonneesPersonnellesService::class)->anonymiser($this->membre);

    $this->post('/connexion', ['telephone' => '77 555 00 11', 'password' => 'motdepasse1']);

    $this->assertGuest();
});

it('laisse le super administrateur traiter la demande depuis le panneau', function () {
    $demande = DemandeSuppression::query()->create([
        'user_id' => $this->user->id,
        'membre_id' => $this->membre->id,
        'statut' => DemandeSuppression::EN_ATTENTE,
    ]);

    $this->actingAs($this->super);

    Livewire::test(ManageDemandesSuppression::class)
        ->callAction(TestAction::make('anonymiser')->table($demande))
        ->assertHasNoActionErrors();

    expect($demande->fresh()->statut)->toBe(DemandeSuppression::TRAITEE)
        ->and($this->membre->fresh()->getAttribute('anonymise_le'))->not->toBeNull();
});

it('réserve l\'écran des demandes au super administrateur', function () {
    $tresorier = User::factory()->create();
    $tresorier->assignRole('jewrine_finance');

    $this->actingAs($tresorier)->get('/admin/demandes-suppression')->assertForbidden();
    $this->actingAs($this->super)->get('/admin/demandes-suppression')->assertOk();
});

it('applique chaque nuit les durées de conservation', function () {
    DB::table('connexions')->insert([
        ['user_id' => $this->user->id, 'telephone' => '+221775550011', 'reussie' => true, 'created_at' => now()->subMonths(13)],
        ['user_id' => $this->user->id, 'telephone' => '+221775550011', 'reussie' => true, 'created_at' => now()->subMonth()],
    ]);

    $ancienRejet = Membre::factory()->create(['statut' => StatutMembre::Rejete]);
    $rejetRecent = Membre::factory()->create(['statut' => StatutMembre::Rejete]);
    DB::table('membres')->where('id', $ancienRejet->id)->update(['updated_at' => now()->subMonths(7)]);

    $this->artisan('donnees:purger')->assertSuccessful();

    expect(DB::table('connexions')->count())->toBe(1)
        ->and($ancienRejet->fresh()->getAttribute('anonymise_le'))->not->toBeNull()
        ->and($rejetRecent->fresh()->getAttribute('anonymise_le'))->toBeNull();
});
