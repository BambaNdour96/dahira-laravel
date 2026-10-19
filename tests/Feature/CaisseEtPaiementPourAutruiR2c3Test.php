<?php

use App\Enums\Sexe;
use App\Enums\StatutMembre;
use App\Enums\TypeTarif;
use App\Models\CartePhysique;
use App\Models\Cellule;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Renouvellement;
use App\Models\Tarif;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CarteService;
use App\Services\PaiementService;
use App\Services\RenouvellementService;
use App\Services\RepresentantFinanceService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/** Une Sokhna validée depuis un an, avec son compte et sa carte. */
function sokhnaR2c3(Cellule $cellule, string $prenom, string $telephone): Membre
{
    $compte = User::factory()->create(['name' => $prenom.' Diop', 'telephone' => $telephone]);
    $compte->assignRole('membre');
    $membre = Membre::factory()->for($compte)->for($cellule)->valide()->create([
        'prenom' => $prenom, 'sexe' => Sexe::Femme, 'telephone' => $telephone, 'valide_le' => now()->subYear(),
    ]);
    $membre->forceFill(['matricule' => 'NDK-'.str_pad((string) $membre->id, 4, '0', STR_PAD_LEFT)])->save();
    app(CarteService::class)->emettre($membre);

    return $membre;
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    $this->travelTo(Carbon::parse('2026-11-02 10:00'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->national = User::factory()->create();
    $this->national->assignRole('jewrine_finance');
    $this->cellule = Cellule::factory()->create(['nom' => 'Médina']);

    $this->representanteMembre = sokhnaR2c3($this->cellule, 'Khady', '+221770000001');
    $this->representante = $this->representanteMembre->user;
    app(RepresentantFinanceService::class)->designer($this->cellule, 'femmes', $this->representante, $this->national);

    $this->awa = sokhnaR2c3($this->cellule, 'Awa', '+221770000002');
    Tarif::query()->create(['type' => TypeTarif::CartePhysique, 'libelle' => 'Carte en plastique', 'montant' => 2000, 'actif' => true]);
    $this->exercice = Exercice::query()->create(['libelle' => '2027', 'date_debut' => '2027-01-01', 'date_fin' => '2027-12-31']);
    app(RenouvellementService::class)->ouvrir($this->exercice, Carbon::parse('2027-01-31'), $this->super);
});

it('laisse la représentante encaisser le renouvellement et la carte en plastique depuis « Ma caisse »', function () {
    $this->actingAs($this->representante->fresh())->get(route('caisse.membre', $this->awa))
        ->assertOk()->assertSee('Renouvellement 2027')->assertSee('Carte en plastique');

    $this->actingAs($this->representante->fresh())->post(route('caisse.renouvellement', $this->awa))->assertRedirect(route('caisse.index'));
    $this->actingAs($this->representante->fresh())->post(route('caisse.plastique', $this->awa))->assertRedirect(route('caisse.index'));
    $this->actingAs($this->representante->fresh())->post(route('caisse.plastique', $this->awa))->assertSessionHas('caisse_erreur', 'Une carte en plastique est déjà commandée.');

    expect(app(RenouvellementService::class)->pour($this->awa)?->statut)->toBe(Renouvellement::RENOUVELE)
        ->and($this->awa->carteActive()->first()?->exercice_id)->toBe($this->exercice->id)
        ->and(CartePhysique::query()->where('membre_id', $this->awa->id)->value('statut'))->toBe(CartePhysique::PAYEE)
        ->and(app(RepresentantFinanceService::class)->enCaisse($this->representante))->toBe(app(PaiementService::class)->montantFraisCarte() + 2000);

    $this->actingAs($this->representante->fresh())->get(route('caisse.membre', $this->awa))->assertDontSee(route('caisse.renouvellement', $this->awa));
});

it('garde les membres non renouvelés dans la caisse, pour encaisser leur renouvellement', function () {
    $this->travelTo(Carbon::parse('2027-02-01 08:00'));
    app(RenouvellementService::class)->routine();
    expect($this->awa->fresh()->statut)->toBe(StatutMembre::NonRenouvele);

    $this->actingAs($this->representante->fresh())->get(route('caisse.index'))->assertSee('Awa');
    $this->actingAs($this->representante->fresh())->post(route('caisse.renouvellement', $this->awa))->assertRedirect(route('caisse.index'));
    expect($this->awa->fresh()->statut)->toBe(StatutMembre::Valide);
});

it('laisse un membre payer le renouvellement et la carte en plastique d\'un autre membre', function () {
    Notification::fake();
    $mari = sokhnaR2c3($this->cellule, 'Modou', '+221770000003');

    $this->actingAs($mari->user)->get(route('autrui.show', ['recherche' => $this->awa->matricule]))
        ->assertOk()->assertSee('Son renouvellement 2027')->assertSee('Sa carte en plastique');

    $this->actingAs($mari->user)->post(route('autrui.renouvellement', $this->awa), ['reference' => 'T_MODOU00000REN1'])->assertRedirect(route('autrui.show'));
    $this->actingAs($mari->user)->post(route('autrui.plastique', $this->awa), ['reference' => 'T_MODOU00000CPH1'])->assertRedirect(route('autrui.show'));

    $renouvellement = Paiement::query()->where('objet', 'renouvellement')->firstOrFail();
    $carte = Paiement::query()->where('objet', 'carte_physique')->firstOrFail();
    expect($renouvellement->membre_id)->toBe($this->awa->id)->and($renouvellement->declare_par)->toBe($mari->user_id)
        ->and($carte->declare_par)->toBe($mari->user_id);
    Notification::assertSentTo($this->awa->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->awa->user)['titre'] === 'Un paiement pour vous');

    // Celui qui a déclaré ne valide pas ; un autre le fait, et le renouvellement suit.
    $mari->user->assignRole('jewrine_finance');
    expect(fn () => app(PaiementService::class)->confirmer($renouvellement, $mari->user->fresh()))->toThrow(DomainException::class, 'Vous avez déclaré ce paiement');
    app(PaiementService::class)->confirmer($renouvellement, $this->national);
    app(PaiementService::class)->confirmer($carte, $this->national);

    expect(app(RenouvellementService::class)->pour($this->awa)?->statut)->toBe(Renouvellement::RENOUVELE)
        ->and(CartePhysique::query()->where('membre_id', $this->awa->id)->exists())->toBeTrue();
    $this->actingAs($mari->user)->get(route('autrui.show', ['recherche' => $this->awa->matricule]))
        ->assertDontSee(route('autrui.renouvellement', $this->awa))->assertDontSee(route('autrui.plastique', $this->awa));
});
