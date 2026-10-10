<?php

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Filament\Widgets\MesTaches;
use App\Models\Carte;
use App\Models\Cellule;
use App\Models\Cotisation;
use App\Models\DemandeSuppression;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Poste;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CompteBureauService;
use App\Services\CotisationService;
use App\Services\DonneesPersonnellesService;
use App\Services\PaiementService;
use App\Services\PresenceService;
use App\Services\StatutMembreService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\ReglesValidationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/*
 * Les grands parcours : chaque test raconte une histoire complète, de bout en bout.
 */

uses(RefreshDatabase::class);

function titresDesNotifications(User $compte): array
{
    return DB::table('notifications')
        ->where('notifiable_id', $compte->id)
        ->where('type', NotificationMembre::class)
        ->pluck('data')
        ->map(fn (string $data): string => json_decode($data, true)['titre'])
        ->all();
}

function compteDuParcours(array $roles, ?string $poste = null, ?Cellule $cellule = null): User
{
    $compte = User::factory()->create(['password' => 'motdepasse1']);
    $compte->assignRole([...$roles, 'membre']);

    $fabrique = Membre::factory()->for($compte);
    if ($cellule !== null) {
        $fabrique = $fabrique->for($cellule);
    }

    $fabrique->valide()->create([
        'valide_le' => now()->subMonths(2),
        'poste_id' => $poste !== null ? Poste::query()->where('nom', $poste)->value('id') : null,
    ]);

    return $compte->fresh();
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class, ReglesValidationSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    RateLimiter::for('connexion', fn () => Limit::none());

    $this->super = User::factory()->create();
    $this->super->assignRole(['super_administrateur', 'administrateur']);

    $this->jewrine = User::factory()->create();
    $this->jewrine->assignRole('responsable_section');
    $this->kaolack = Cellule::factory()->create(['nom' => 'Kaolack', 'responsable_id' => $this->jewrine->id]);
    $this->dakar = Cellule::factory()->create(['nom' => 'Dakar']);

    $this->tresorier = compteDuParcours(['tresorier'], null, $this->dakar);
});

it('accompagne un membre de sa demande jusqu\'à l\'effacement de ses données', function () {
    // 1. La demande en attente, puis acceptée par le Jewrine de la cellule.
    $compte = User::factory()->create(['telephone' => '+221771230001', 'password' => 'motdepasse1']);
    $compte->assignRole('membre');
    $membre = Membre::factory()->for($compte)->for($this->kaolack)->create(['statut' => StatutMembre::EnAttente]);

    expect($this->jewrine->can('changerStatut', [$membre, StatutMembre::Accepte]))->toBeTrue();
    app(StatutMembreService::class)->changer($membre, StatutMembre::Accepte, $this->jewrine);

    // 2. Les frais de carte, déclarés par Wave puis confirmés par le Jewrine finance.
    $this->actingAs($compte->fresh())->post('/mon-espace/paiement', ['reference' => 'T_PARCOURS00000001', 'numero_wave' => '77 123 00 01'])
        ->assertRedirect(route('espace'));
    $fraisDeCarte = Paiement::query()->where('membre_id', $membre->id)->firstOrFail();
    app(PaiementService::class)->confirmer($fraisDeCarte, $this->tresorier);

    $membre->refresh();
    expect($membre->statut)->toBe(StatutMembre::Valide)
        ->and($membre->matricule)->toStartWith('NDK-')
        ->and($fraisDeCarte->fresh()->numero_recu)->toStartWith('INS-');

    // 3. La carte, consultée par le membre puis vérifiée au scan.
    $carte = Carte::query()->where('membre_id', $membre->id)->where('statut', 'active')->firstOrFail();
    $this->actingAs($compte->fresh())->get('/ma-carte')->assertOk()->assertSee($membre->matricule);
    $this->get(route('verification', $carte->jeton))->assertOk()->assertSee('Membre actif');

    // 4. Deux mois de cotisation, avec leur reçu.
    $this->actingAs($compte->fresh())->post('/mes-cotisations', ['reference' => 'T_PARCOURS00000002', 'nombre_mois' => 2, 'numero_wave' => '77 123 00 01'])
        ->assertRedirect(route('cotisations'));
    $cotisation = Paiement::query()->where('membre_id', $membre->id)->where('objet', 'cotisation')->firstOrFail();
    app(PaiementService::class)->confirmer($cotisation, $this->tresorier);

    expect(Cotisation::query()->where('membre_id', $membre->id)->count())->toBe(2)
        ->and($cotisation->fresh()->numero_recu)->toStartWith('COT-');
    $this->actingAs($compte->fresh())->get('/mes-recus')
        ->assertSee($fraisDeCarte->fresh()->numero_recu)
        ->assertSee($cotisation->fresh()->numero_recu);

    // 5. La présence à un événement, enregistrée au scan.
    $this->actingAs($this->super);
    $evenement = Evenement::query()->create(['titre' => 'Magal 2026', 'lieu' => 'Kaolack', 'debut' => now()->addHour()]);
    app(PresenceService::class)->marquer($evenement, $membre, $this->super);
    $this->actingAs($compte->fresh())->get('/evenements')->assertSee('Magal 2026')->assertSee('Présent ✓');

    // 6. Les notifications reçues tout au long du parcours.
    expect(titresDesNotifications($compte))
        ->toContain('Votre demande est acceptée')
        ->toContain('Bienvenue dans la Daara !')
        ->toContain('Paiement confirmé');

    // 7. Le téléchargement de ses données, puis la demande de suppression et l'effacement.
    expect($this->actingAs($compte->fresh())->get(route('donnees.export'))->json('fiche_membre.matricule'))->toBe($membre->matricule);

    $this->actingAs($compte->fresh())->post('/mon-compte/suppression', ['mot_de_passe' => 'motdepasse1', 'confirmation' => '1'])
        ->assertRedirect(route('espace'));
    app(DonneesPersonnellesService::class)->traiterDemande(DemandeSuppression::query()->firstOrFail(), $this->super);

    $this->post('/deconnexion');
    $this->post('/connexion', ['telephone' => '77 123 00 01', 'password' => 'motdepasse1']);
    $this->assertGuest();
    $this->get(route('verification', $carte->jeton))->assertSee('Carte non valide');
    expect(Paiement::query()->where('membre_id', $membre->id)->where('statut', StatutPaiement::Confirme->value)->count())->toBe(2);
});

it('fait fonctionner le bureau : tâches, cellules, validation croisée et relances', function () {
    // Le Jewrine voit la nouvelle demande de sa cellule dans ses tâches.
    $demande = Membre::factory()->for($this->kaolack)->create(['statut' => StatutMembre::EnAttente]);
    $ailleurs = Membre::factory()->for($this->dakar)->create(['statut' => StatutMembre::EnAttente]);

    expect(DB::table('notifications')->where('notifiable_id', $this->jewrine->id)->count())->toBe(1);
    $this->actingAs($this->jewrine);
    Livewire::test(MesTaches::class)->assertSee('Demandes à examiner');

    expect($this->jewrine->can('changerStatut', [$demande, StatutMembre::Accepte]))->toBeTrue()
        ->and($this->jewrine->can('changerStatut', [$ailleurs, StatutMembre::Accepte]))->toBeFalse();

    // Le paiement du Jewrine finance est confié au Secrétaire général, qui le valide.
    $jewrineFinance = compteDuParcours(['tresorier'], 'Jewrine finance', $this->dakar);
    $secretaire = compteDuParcours(['secretaire_general', 'administrateur'], 'Secrétaire général', $this->dakar);

    $paiement = app(CotisationService::class)->declarer($jewrineFinance->membre, 'T_PARCOURS00000003', 1);
    expect(DB::table('notifications')->where('notifiable_id', $secretaire->id)->count())->toBe(1);

    expect(fn () => app(PaiementService::class)->confirmer($paiement, $this->tresorier))->toThrow(DomainException::class);
    app(PaiementService::class)->confirmer($paiement, $secretaire);
    expect($paiement->fresh()->statut)->toBe(StatutPaiement::Confirme);

    // Un retardataire est relancé sur WhatsApp, et la relance est notée.
    $retardataire = Membre::factory()->for($this->dakar)->valide()->create(['valide_le' => now()->subMonths(4), 'telephone' => '+221775550099']);

    $reponse = $this->actingAs($this->tresorier)->get(route('relances.whatsapp', $retardataire));
    expect($reponse->headers->get('Location'))->toStartWith('https://wa.me/221775550099')
        ->and($retardataire->fresh()->getAttribute('relance_le'))->not->toBeNull();
});

it('protège un compte : blocage, mot de passe provisoire, changement obligatoire et inactivité', function () {
    $compte = compteDuParcours([], null, $this->kaolack);
    $compte->forceFill(['telephone' => '+221779990011'])->save();

    // 5 mauvais mots de passe : le compte est bloqué, même avec le bon, et le super administrateur est alerté.
    foreach (range(1, 5) as $essai) {
        $this->post('/connexion', ['telephone' => '77 999 00 11', 'password' => 'mauvais1']);
    }
    $this->post('/connexion', ['telephone' => '77 999 00 11', 'password' => 'motdepasse1'])->assertSessionHasErrors('telephone');
    $this->assertGuest();
    expect(DB::table('notifications')->where('notifiable_id', $this->super->id)->count())->toBeGreaterThanOrEqual(1);

    // Le Jewrine donne un mot de passe provisoire, qui débloque le compte.
    $provisoire = app(CompteBureauService::class)->reinitialiserMotDePasse($compte);
    $this->post('/connexion', ['telephone' => '77 999 00 11', 'password' => $provisoire]);
    $this->assertAuthenticatedAs($compte);

    // Le changement est obligatoire, puis déconnecte la personne.
    $this->get('/mon-espace')->assertRedirect(route('mot-de-passe.edit'));
    $this->put('/mon-compte/mot-de-passe', [
        'mot_de_passe_actuel' => $provisoire,
        'password' => 'nouveaumotdepasse2',
        'password_confirmation' => 'nouveaumotdepasse2',
    ]);
    $this->assertGuest();

    // Reconnexion avec le nouveau mot de passe, puis déconnexion après 7 minutes d'inactivité.
    $this->post('/connexion', ['telephone' => '77 999 00 11', 'password' => 'nouveaumotdepasse2']);
    $this->assertAuthenticatedAs($compte);
    $this->get('/mon-espace')->assertOk();

    $this->travel(3)->minutes();
    $this->get('/mon-espace')->assertRedirect(route('login'));
});
