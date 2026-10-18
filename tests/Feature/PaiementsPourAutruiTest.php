<?php

use App\Enums\ModeCollecte;
use App\Enums\ProgrammeCollecte;
use App\Enums\StatutCollecte;
use App\Enums\StatutMembre;
use App\Models\Collecte;
use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CollecteService;
use App\Services\CotisationService;
use App\Services\FamilleService;
use App\Services\PaiementService;
use App\Services\StatutMembreService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');

    $this->jewrine = User::factory()->create();
    $this->cellule = celluleDirigeePar($this->jewrine);
    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');

    $this->compteMari = User::factory()->create(['name' => 'Modou Diop', 'telephone' => '+221771230000']);
    $this->compteMari->assignRole('membre');
    $this->mari = Membre::factory()->for($this->compteMari)->valide()->create(['prenom' => 'Modou', 'cellule_id' => $this->cellule->id, 'telephone' => '+221771230000', 'valide_le' => now()]);

    $this->compteEpouse = User::factory()->create(['name' => 'Aïssatou Diop', 'telephone' => '+221775550000']);
    $this->compteEpouse->assignRole('membre');
    $this->epouse = Membre::factory()->for($this->compteEpouse)->valide()->create([
        'prenom' => 'Aïssatou', 'cellule_id' => $this->cellule->id, 'telephone' => '+221775550000', 'valide_le' => now(),
    ]);
    $this->epouse->forceFill(['matricule' => 'NDK-0042'])->save();

    $this->collecte = Collecte::query()->create([
        'titre' => 'Magal de Touba 2026', 'programme' => ProgrammeCollecte::Magal, 'mode' => ModeCollecte::EngagementLibre,
        'ouverture' => now()->subDay(), 'statut' => StatutCollecte::Ouverte,
    ]);
});

it('retrouve un membre par son matricule ou son téléphone, sans montrer ses montants', function () {
    app(CollecteService::class)->fixerParMembre($this->collecte, $this->epouse, 25000);

    foreach (['ndk-0042', '77 555 00 00'] as $recherche) {
        $this->actingAs($this->compteMari)->get(route('autrui.show', ['recherche' => $recherche]))
            ->assertOk()->assertSee('Aïssatou')->assertSee('NDK-0042')->assertSee('Magal de Touba 2026')
            ->assertDontSee('25 000');
    }

    $this->actingAs($this->compteMari)->get(route('autrui.show', ['recherche' => 'NDK-9999']))->assertSee('Aucun membre validé ne correspond');
    $this->actingAs($this->compteMari)->get(route('autrui.show', ['recherche' => '77 123 00 00']))->assertSee('C\'est votre propre fiche');

    $enfant = app(FamilleService::class)->ajouterProche($this->mari, [
        'prenom' => 'Fallou', 'nom' => 'Diop', 'sexe' => 'H', 'date_naissance' => now()->subYears(7)->toDateString(), 'lien_famille' => 'enfant',
    ], UploadedFile::fake()->image('f.jpg'));
    $enfant->forceFill(['statut' => StatutMembre::Valide, 'matricule' => 'NDK-0050'])->save();

    $this->actingAs($this->compteMari)->get(route('autrui.show', ['recherche' => 'NDK-0050']))->assertSee('payez pour lui depuis « Ma famille »');
    $this->actingAs($this->compteEpouse)->get(route('autrui.show', ['recherche' => 'NDK-0050']))->assertSee('passe par le membre qui gère sa fiche');
});

it('laisse un membre payer la cotisation de son épouse : elle est prévenue et reçoit son reçu', function () {
    Notification::fake();

    $this->actingAs($this->compteMari)
        ->post(route('autrui.cotisation', $this->epouse), ['nombre_mois' => 3, 'reference' => 'T_MODOU123456789'])
        ->assertRedirect(route('autrui.show'));

    $paiement = Paiement::query()->where('membre_id', $this->epouse->id)->firstOrFail();
    expect($paiement->objet)->toBe('cotisation')->and($paiement->montant)->toBe(3000)->and($paiement->declare_par)->toBe($this->compteMari->id);
    Notification::assertSentTo($this->compteEpouse, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->compteEpouse)['titre'] === 'Un paiement pour vous'
        && str_contains($avis->toArray($this->compteEpouse)['message'], 'Modou Diop'));

    app(PaiementService::class)->confirmer($paiement, $this->tresorier);

    Notification::assertSentTo($this->compteEpouse, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->compteEpouse)['titre'] === 'Paiement confirmé'
        && str_contains($avis->toArray($this->compteEpouse)['message'], 'versé pour vous par Modou Diop'));
    Notification::assertSentTo($this->compteMari, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->compteMari)['titre'] === 'Paiement confirmé'
        && str_contains($avis->toArray($this->compteMari)['message'], 'Votre paiement pour'));

    expect(Cotisation::query()->where('membre_id', $this->epouse->id)->count())->toBe(3);
    $this->actingAs($this->compteEpouse)->get(route('recus.show', $paiement))->assertOk();
    $this->actingAs($this->compteMari)->get(route('recus.show', $paiement))->assertOk();
    $this->actingAs($this->jewrine)->get(route('recus.show', $paiement))->assertForbidden();
});

it('laisse un membre verser le Jàyante d\'un autre, sans jamais lui révéler ce qui reste', function () {
    $this->actingAs($this->compteMari)
        ->post(route('autrui.versement', [$this->epouse, $this->collecte]), ['montant' => 5000, 'reference' => 'T_MODOU000000001'])
        ->assertSessionHasErrors(['montant' => 'Ce membre n\'a pas encore fixé son Jàyante pour cette collecte.']);

    app(CollecteService::class)->fixerParMembre($this->collecte, $this->epouse, 10000);

    $this->actingAs($this->compteMari)
        ->post(route('autrui.versement', [$this->epouse, $this->collecte]), ['montant' => 15000, 'reference' => 'T_MODOU000000002'])
        ->assertSessionHasErrors(['montant' => 'Ce montant dépasse ce qu\'il reste à verser à ce membre. Demandez-lui le montant exact.']);

    $this->actingAs($this->compteMari)
        ->post(route('autrui.versement', [$this->epouse, $this->collecte]), ['montant' => 4000, 'reference' => 'T_MODOU000000003'])
        ->assertRedirect(route('autrui.show'));

    $versement = Paiement::query()->where('membre_id', $this->epouse->id)->where('objet', 'collecte')->firstOrFail();
    expect($versement->montant)->toBe(4000)->and($versement->declare_par)->toBe($this->compteMari->id);
});

it('ne laisse personne valider un paiement qu\'il a déclaré pour un autre membre', function () {
    $this->compteMari->assignRole('jewrine_finance');
    $paiement = app(CotisationService::class)->declarer($this->epouse, 'T_MODOU999999999', 1, null, $this->compteMari);

    expect(fn () => app(PaiementService::class)->confirmer($paiement, $this->compteMari->fresh()))
        ->toThrow(DomainException::class, 'Vous avez déclaré ce paiement pour un autre membre')
        ->and($this->compteMari->fresh()->can('update', $paiement))->toBeTrue();

    app(PaiementService::class)->refuser($paiement, $this->tresorier, 'Référence introuvable sur le relevé');
    expect($paiement->fresh()->motif_refus)->toBe('Référence introuvable sur le relevé');
});

it('laisse le parent fixer et verser le Jàyante de son enfant, depuis « Ma famille »', function () {
    $enfant = app(FamilleService::class)->ajouterProche($this->mari, [
        'prenom' => 'Fallou', 'nom' => 'Diop', 'sexe' => 'H', 'date_naissance' => now()->subYears(7)->toDateString(), 'lien_famille' => 'enfant',
    ], UploadedFile::fake()->image('f.jpg'));
    app(StatutMembreService::class)->changer($enfant, StatutMembre::Valide, $this->jewrine);

    $this->actingAs($this->compteMari)->get(route('famille.engagements', $enfant))->assertOk()
        ->assertSee('Sass et Jàyante de Fallou')->assertSee('Fixez son Jàyante');
    $this->actingAs($this->compteMari)->post(route('famille.engagements.fixer', [$enfant, $this->collecte]), ['montant' => 5000])
        ->assertRedirect(route('famille.engagements', $enfant));
    $this->actingAs($this->compteMari)->post(route('famille.engagements.versement', [$enfant, $this->collecte]), ['montant' => 2000, 'reference' => 'T_FALLOU12345678'])
        ->assertRedirect(route('famille.engagements', $enfant));

    $versement = Paiement::query()->where('membre_id', $enfant->id)->where('objet', 'collecte')->firstOrFail();
    expect($versement->declare_par)->toBe($this->compteMari->id)
        ->and(app(CollecteService::class)->engagementDe($this->collecte, $enfant)?->montant)->toBe(5000);

    $this->actingAs($this->compteEpouse)->get(route('famille.engagements', $enfant))->assertForbidden();
    $this->actingAs($this->compteEpouse)->post(route('famille.engagements.fixer', [$enfant, $this->collecte]), ['montant' => 9000])->assertForbidden();
});
