<?php

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\User;
use App\Services\PaiementService;
use App\Services\StatutMembreService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('tresorier');

    $this->user = User::factory()->create();
    $this->user->assignRole('membre');
    $this->membre = Membre::factory()->for($this->user)->accepte()->create();
});

it('n\'attribue ni matricule ni carte à l\'acceptation', function () {
    $membre = Membre::factory()->create();
    app(StatutMembreService::class)->changer($membre, StatutMembre::Accepte, null);
    $membre->refresh();

    expect($membre->statut)->toBe(StatutMembre::Accepte)
        ->and($membre->matricule)->toBeNull()
        ->and($membre->carteActive)->toBeNull()
        ->and($membre->accepte_le)->not->toBeNull();
});

it('montre les frais de carte au membre accepté', function () {
    $this->actingAs($this->user)->get('/mon-espace')
        ->assertOk()
        ->assertSee('1 000 FCFA');
});

it('enregistre la déclaration de paiement', function () {
    $this->actingAs($this->user)->post('/mon-espace/paiement', ['reference' => 't_abc 123 456 789'])
        ->assertRedirect(route('espace'));

    $paiement = Paiement::query()->firstOrFail();

    expect($paiement->reference)->toBe('T_ABC123456789')
        ->and($paiement->statut)->toBe(StatutPaiement::Declare)
        ->and($paiement->montant)->toBe(1000);
});

it('refuse une référence déjà utilisée', function () {
    app(PaiementService::class)->declarer(Membre::factory()->accepte()->create(), 'T_ABC123456789');

    $this->actingAs($this->user)->post('/mon-espace/paiement', ['reference' => 'T_ABC123456789'])
        ->assertSessionHasErrors('reference');
});

it('valide le membre et émet sa carte à la confirmation du trésorier', function () {
    $service = app(PaiementService::class);
    $service->confirmer($service->declarer($this->membre, 'T_XYZ789'), $this->tresorier);

    $this->membre->refresh();

    expect($this->membre->statut)->toBe(StatutMembre::Valide)
        ->and($this->membre->matricule)->not->toBeNull()
        ->and($this->membre->carteActive)->not->toBeNull();
});

it('laisse le membre déclarer à nouveau après un refus', function () {
    $service = app(PaiementService::class);
    $service->refuser($service->declarer($this->membre, 'T_FAUX'), $this->tresorier, 'Référence introuvable');

    $this->actingAs($this->user)->post('/mon-espace/paiement', ['reference' => 'T_BON123456789X'])
        ->assertRedirect(route('espace'));

    expect($this->membre->refresh()->statut)->toBe(StatutMembre::Accepte);
});

it('valide directement un paiement en espèces', function () {
    app(PaiementService::class)->enregistrerEspeces($this->membre, $this->tresorier);

    expect($this->membre->refresh()->statut)->toBe(StatutMembre::Valide);
});

it('fait expirer une demande non payée après 10 jours', function () {
    $this->membre->forceFill(['accepte_le' => now()->subDays(11)])->save();

    $this->artisan('membres:expirer')->assertSuccessful();

    expect($this->membre->refresh()->statut)->toBe(StatutMembre::Expire);
});

it('n\'expire pas une demande dont le paiement est en vérification', function () {
    $this->membre->forceFill(['accepte_le' => now()->subDays(11)])->save();
    app(PaiementService::class)->declarer($this->membre, 'T_ENCOURS');

    $this->artisan('membres:expirer');

    expect($this->membre->refresh()->statut)->toBe(StatutMembre::Accepte);
});

it('permet de refaire sa demande après expiration', function () {
    $this->membre->forceFill(['statut' => StatutMembre::Expire])->save();

    $this->actingAs($this->user)->post('/mon-espace/relancer')->assertRedirect(route('espace'));

    expect($this->membre->refresh()->statut)->toBe(StatutMembre::EnAttente);
});

it('refuse une déclaration à un membre non encore accepté', function () {
    $user = User::factory()->create();
    $user->assignRole('membre');
    Membre::factory()->for($user)->create();

    $this->actingAs($user)->post('/mon-espace/paiement', ['reference' => 'T_123456'])
        ->assertSessionHasErrors('reference');
});

it('réserve l\'écran des paiements au trésorier et à l\'administrateur', function () {
    $jewrine = User::factory()->create();
    $jewrine->assignRole('responsable_section');

    $this->actingAs($this->tresorier)->get('/admin/paiements')->assertOk();
    $this->actingAs($jewrine)->get('/admin/paiements')->assertForbidden();
});

it('laisse un membre en attente ou accepté se reconnecter pour suivre sa demande', function (string $etat) {
    $user = User::factory()->create(['telephone' => '+221761112233', 'password' => 'motdepasse1']);
    $user->assignRole('membre');

    $fabrique = Membre::factory()->for($user);
    $etat === 'accepte' ? $fabrique->accepte()->create() : $fabrique->create();

    // Première connexion
    $this->post('/connexion', ['telephone' => '76 111 22 33', 'password' => 'motdepasse1'])
        ->assertRedirect(route('espace'));
    $this->get('/mon-espace')
        ->assertOk()
        ->assertSee($etat === 'accepte' ? 'FCFA' : 'en cours de vérification');

    // Déconnexion
    $this->post('/deconnexion')->assertRedirect(route('accueil'));
    $this->assertGuest();

    // Retour plus tard : la personne se reconnecte et retrouve sa demande
    $this->post('/connexion', ['telephone' => '761112233', 'password' => 'motdepasse1'])
        ->assertRedirect(route('espace'));
    $this->assertAuthenticatedAs($user);
    $this->get('/mon-espace')
        ->assertOk()
        ->assertSee($etat === 'accepte' ? 'FCFA' : 'en cours de vérification');
})->with(['en_attente', 'accepte']);

it('donne au membre le reçu de son inscription', function () {
    app(PaiementService::class)->enregistrerEspeces($this->membre, $this->tresorier);

    $this->actingAs($this->user)->get('/mon-espace')
        ->assertOk()
        ->assertSee("Mon reçu d'inscription", false)
        ->assertSee('Mes reçus');
});

it('affiche le bouton Wave des frais de carte avec le lien « frais de carte » des réglages', function () {
    Parametre::query()->updateOrCreate(['cle' => 'wave_lien_frais_carte'], ['valeur' => 'https://pay.wave.com/m/FRAISCARTE', 'libelle' => 'Lien Wave : frais de carte']);
    Parametre::query()->updateOrCreate(['cle' => 'wave_lien_paiement'], ['valeur' => null, 'libelle' => 'Ancien lien Wave']);

    $this->actingAs($this->user)->get('/mon-espace')
        ->assertSee('Payer avec Wave')
        ->assertSee('https://pay.wave.com/m/FRAISCARTE', false);
});
