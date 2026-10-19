<?php

use App\Enums\StatutCarte;
use App\Enums\StatutMembre;
use App\Filament\Resources\CampagnesRenouvellement\Pages\ListCampagnesRenouvellement;
use App\Filament\Resources\CampagnesRenouvellement\Widgets\RenouvellementsDeLaCampagne;
use App\Filament\Resources\Membres\Pages\ListMembres;
use App\Models\CampagneRenouvellement;
use App\Models\Carte;
use App\Models\Exercice;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Renouvellement;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Observers\AvisMembreObserver;
use App\Services\CarteService;
use App\Services\CotisationService;
use App\Services\FamilleService;
use App\Services\PaiementService;
use App\Services\RenouvellementService;
use App\Services\StatutMembreService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->travelTo(Carbon::parse('2026-11-02 10:00'));

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
    $this->jewrine = User::factory()->create(['name' => 'Jewrine Médina']);
    $this->cellule = celluleDirigeePar($this->jewrine, ['nom' => 'Médina']);
    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');
    $this->exercice = Exercice::query()->create(['libelle' => '2027', 'date_debut' => '2027-01-01', 'date_fin' => '2027-12-31']);

    $compte = User::factory()->create(['name' => 'Awa Ndiaye']);
    $compte->assignRole('membre');
    $this->ancienne = Membre::factory()->for($compte)->valide()->create(['prenom' => 'Awa', 'cellule_id' => $this->cellule->id, 'valide_le' => now()->subYear()]);
    app(CarteService::class)->emettre($this->ancienne);

    $recent = User::factory()->create();
    $recent->assignRole('membre');
    $this->recente = Membre::factory()->for($recent)->valide()->create(['prenom' => 'Binta', 'cellule_id' => $this->cellule->id, 'valide_le' => now()->subMonth()]);
    app(CarteService::class)->emettre($this->recente);

    $this->service = app(RenouvellementService::class);
});

function ouvrirCampagne(): CampagneRenouvellement
{
    return app(RenouvellementService::class)->ouvrir(Exercice::query()->where('libelle', '2027')->firstOrFail(), Carbon::parse('2027-01-31'), User::role('super_administrateur')->firstOrFail());
}

it('fait ouvrir la campagne par le seul super administrateur, avec le renouvellement d\'office des membres récents', function () {
    Notification::fake();
    $national = nommerPourLeTest(User::factory()->create(), 'jewrine_national');
    expect(fn () => $this->service->ouvrir($this->exercice, Carbon::parse('2027-01-31'), $national))->toThrow(DomainException::class, 'Seul le super administrateur');

    $this->actingAs($this->super);
    Livewire::test(ListCampagnesRenouvellement::class)
        ->callAction('ouvrir', ['exercice_id' => $this->exercice->id, 'date_limite' => '2027-01-31'])
        ->assertHasNoActionErrors()
        ->assertActionHidden('ouvrir');

    $campagne = CampagneRenouvellement::query()->firstOrFail();
    expect($this->service->pour($this->ancienne)?->statut)->toBe(Renouvellement::A_FAIRE)
        ->and($this->service->pour($this->recente)?->statut)->toBe(Renouvellement::D_OFFICE)
        ->and($this->recente->carteActive()->first()?->exercice_id)->toBe($this->exercice->id)
        ->and(fn () => ouvrirCampagne())->toThrow(DomainException::class, 'déjà en cours');
    Notification::assertSentTo($this->ancienne->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->toArray($this->ancienne->user)['titre'], 'campagne de renouvellement 2027 est ouverte'));
    Notification::assertNotSentTo($this->recente->user, NotificationMembre::class);

    // Un membre validé pendant la campagne est renouvelé d'office.
    $nouvelle = Membre::factory()->for(User::factory())->accepte()->create(['cellule_id' => $this->cellule->id]);
    app(StatutMembreService::class)->changer($nouvelle, StatutMembre::Valide, $this->tresorier);
    expect($this->service->pour($nouvelle)?->statut)->toBe(Renouvellement::D_OFFICE)
        ->and($nouvelle->carteActive()->first()?->exercice_id)->toBe($this->exercice->id)
        ->and($campagne->renouvellements()->count())->toBe(3);
});

it('renouvelle automatiquement quand la carte ne change pas : fiche vérifiée, paiement validé, nouvelle carte de l\'année', function () {
    Notification::fake();
    ouvrirCampagne();
    $ancienneCarte = $this->ancienne->carteActive()->firstOrFail();
    $compte = $this->ancienne->user;

    $this->actingAs($compte)->get(route('espace'))->assertSee('Renouveler mon adhésion 2027');
    $this->actingAs($compte)->get(route('renouvellement.show'))->assertOk()->assertSee('Vérifier la fiche')->assertSee('Tout est exact');
    $this->actingAs($compte)->post(route('renouvellement.payer'), ['reference' => 'T_AWA0000000000R'])->assertSessionHasErrors(['renouvellement' => 'Vérifiez d\'abord la fiche.']);
    $this->actingAs($compte)->post(route('renouvellement.verifier'))->assertRedirect(route('renouvellement.show'));
    $this->actingAs($compte)->post(route('renouvellement.payer'), ['reference' => 'T_AWA0000000000R'])->assertRedirect(route('renouvellement.show'));

    $paiement = Paiement::query()->where('objet', 'renouvellement')->firstOrFail();
    expect($paiement->montant)->toBe(app(PaiementService::class)->montantFraisCarte())->and($paiement->declare_par)->toBe($compte->id);

    app(PaiementService::class)->confirmer($paiement, $this->tresorier);

    $nouvelleCarte = $this->ancienne->carteActive()->firstOrFail();
    expect($this->service->pour($this->ancienne)?->statut)->toBe(Renouvellement::RENOUVELE)
        ->and($ancienneCarte->fresh()->statut)->toBe(StatutCarte::Revoquee)
        ->and($ancienneCarte->fresh()->motif_revocation)->toBe('Renouvellement 2027')
        ->and($nouvelleCarte->exercice_id)->toBe($this->exercice->id)
        ->and($paiement->fresh()->numero_recu)->toStartWith('REN-');
    Notification::assertSentTo($compte, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($compte)['titre'] === 'Adhésion 2027 renouvelée');
    Notification::assertNotSentTo($compte, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($compte)['titre'] === 'Votre carte a été remplacée');
    $this->actingAs($compte)->get(route('espace'))->assertDontSee('Renouveler mon adhésion 2027');
});

it('fait revalider par le Jewrine quand la carte change, et signaler une erreur de fiche', function () {
    Notification::fake();
    $campagne = ouvrirCampagne();
    $renouvellement = $this->service->pour($this->ancienne);

    $this->actingAs($this->ancienne->user)->post(route('renouvellement.signaler'), ['message' => 'Mon nom s\'écrit NDIAYE avec un Y'])->assertRedirect(route('renouvellement.show'));
    Notification::assertSentTo($this->jewrine, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_contains($avis->toArray($this->jewrine)['message'], 'NDIAYE avec un Y'));

    $this->ancienne->update(['nom' => 'Ndiayé']);
    $this->service->verifierFiche($renouvellement, $this->ancienne->user);
    app(PaiementService::class)->confirmer($this->service->declarer($renouvellement->fresh(), 'T_AWA0000000001R', null, $this->ancienne->user), $this->tresorier);

    expect($renouvellement->fresh()->statut)->toBe(Renouvellement::A_REVALIDER)
        ->and($this->ancienne->carteActive()->first()?->exercice_id)->toBeNull();
    Notification::assertSentTo($this->jewrine, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->jewrine)['titre'], 'Renouvellement à revalider'));

    expect(fn () => $this->service->revalider($renouvellement->fresh(), $this->ancienne->user))->toThrow(DomainException::class);

    $this->actingAs($this->jewrine);
    Livewire::test(RenouvellementsDeLaCampagne::class, ['record' => $campagne])
        ->assertSee('Awa')
        ->callAction(TestAction::make('revalider')->table($renouvellement->fresh()))
        ->assertHasNoActionErrors();

    expect($renouvellement->fresh()->statut)->toBe(Renouvellement::RENOUVELE)
        ->and($renouvellement->fresh()->revalide_par)->toBe($this->jewrine->id)
        ->and($this->ancienne->carteActive()->first()?->exercice_id)->toBe($this->exercice->id);
});

it('relance à l\'approche de la date limite, puis rend « non renouvelé » sans rien perdre ; le paiement réactive', function () {
    Notification::fake();
    ouvrirCampagne();
    $carte = $this->ancienne->carteActive()->firstOrFail();

    $this->travelTo(Carbon::parse('2027-01-01 08:00'));
    expect($this->service->routine())->toBe(['relances' => 1, 'non_renouveles' => 0])
        ->and($this->service->routine())->toBe(['relances' => 0, 'non_renouveles' => 0]);
    $this->travelTo(Carbon::parse('2027-01-24 08:00'));
    expect($this->service->routine()['relances'])->toBe(1);
    $this->travelTo(Carbon::parse('2027-01-30 08:00'));
    expect($this->service->routine()['relances'])->toBe(1);

    $this->travelTo(Carbon::parse('2027-02-01 08:00'));
    $this->artisan('renouvellement:routine')->assertSuccessful();

    $membre = $this->ancienne->fresh();
    expect($membre->statut)->toBe(StatutMembre::NonRenouvele)
        ->and($carte->fresh()->statut)->toBe(StatutCarte::Revoquee)
        ->and($carte->fresh()->motif_revocation)->toBe(AvisMembreObserver::NON_RENOUVELEE)
        ->and($this->service->pour($membre)?->statut)->toBe(Renouvellement::NON_RENOUVELE)
        ->and($this->service->pour($this->recente)?->statut)->toBe(Renouvellement::D_OFFICE)
        ->and($this->recente->fresh()->statut)->toBe(StatutMembre::Valide)
        ->and(CampagneRenouvellement::query()->firstOrFail()->estClose())->toBeTrue()
        ->and($this->super->can('changerStatut', [$membre, StatutMembre::Valide]))->toBeFalse();
    Notification::assertSentTo($this->ancienne->user, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->ancienne->user)['titre'] === 'Adhésion non renouvelée');
    Notification::assertSentTo($this->jewrine, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->jewrine)['titre'] === 'Renouvellement 2027 : 1 non renouvelé(s)');

    // Il paie en mars : réactivé, nouvelle carte 2027 ; les cotisations des mois « non renouvelé » restent dues.
    $this->travelTo(Carbon::parse('2027-03-10 08:00'));
    $avant = count(app(CotisationService::class)->moisEnRetard($membre->fresh()));
    $this->actingAs($this->tresorier);
    Livewire::test(ListMembres::class)->callAction(TestAction::make('renouvellementEspeces')->table($membre))->assertHasNoActionErrors();

    $membre = $membre->fresh();
    expect($membre->statut)->toBe(StatutMembre::Valide)
        ->and($membre->carteActive()->first()?->exercice_id)->toBe($this->exercice->id)
        ->and($this->service->pour($membre)?->statut)->toBe(Renouvellement::RENOUVELE)
        ->and($avant)->toBe(0)
        ->and(collect(app(CotisationService::class)->moisEnRetard($membre))->map->format('Y-m')->all())->toContain('2027-01', '2027-02');
});

it('laisse le parent renouveler pour un proche, depuis « Ma famille »', function () {
    $enfant = app(FamilleService::class)->ajouterProche($this->ancienne, [
        'prenom' => 'Fallou', 'nom' => 'Ndiaye', 'sexe' => 'H', 'date_naissance' => now()->subYears(8)->toDateString(), 'lien_famille' => 'enfant',
    ], UploadedFile::fake()->image('f.jpg'));
    $enfant->forceFill(['statut' => StatutMembre::Valide, 'valide_le' => now()->subYear()])->save();
    ouvrirCampagne();

    $parent = $this->ancienne->user;
    $this->actingAs($parent)->get(route('famille.show', $enfant))->assertSee('Renouvellement 2027 : À faire');
    $this->actingAs($parent)->get(route('famille.renouvellement', $enfant))->assertOk()->assertSee('Renouvellement de Fallou');
    $this->actingAs($parent)->post(route('famille.renouvellement.verifier', $enfant))->assertRedirect(route('famille.renouvellement', $enfant));
    $this->actingAs($parent)->post(route('famille.renouvellement.payer', $enfant), ['reference' => 'T_FALLOU0000001R'])->assertRedirect(route('famille.renouvellement', $enfant));
    expect(Paiement::query()->where('membre_id', $enfant->id)->where('objet', 'renouvellement')->value('declare_par'))->toBe($parent->id);

    $this->actingAs($this->recente->user)->get(route('famille.renouvellement', $enfant))->assertForbidden();
});

it('fait commencer les cotisations d\'un nouveau membre au mois qui suit sa validation', function () {
    $membre = Membre::factory()->valide()->create(['valide_le' => Carbon::parse('2026-03-15')]);

    expect(app(CotisationService::class)->debut($membre)?->format('Y-m'))->toBe('2026-04');
});
