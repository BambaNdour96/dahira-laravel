<?php

use App\Enums\LienFamille;
use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Filament\Resources\Membres\Pages\CreateMembre;
use App\Filament\Resources\Membres\Pages\EditMembre;
use App\Models\Cellule;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\FamilleService;
use App\Services\PaiementService;
use App\Services\StatutMembreService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->jewrine = User::factory()->create();
    $this->jewrine->assignRole('membre');
    $this->cellule = celluleDirigeePar($this->jewrine, ['nom' => 'Médina']);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');

    $this->parent = User::factory()->create(['telephone' => '+221771112233']);
    $this->parent->assignRole('membre');
    $this->moussa = Membre::factory()->for($this->parent)->valide()->create([
        'prenom' => 'Moussa', 'cellule_id' => $this->cellule->id, 'telephone' => '+221771112233', 'valide_le' => now(),
    ]);
    $this->familles = app(FamilleService::class);
});

/** Le formulaire « Ajouter un proche », tel que le téléphone l'envoie. */
function ficheDeProche(array $autres = []): array
{
    return [
        'prenom' => 'Awa', 'nom' => 'Ndiaye', 'sexe' => 'F', 'lien_famille' => 'enfant',
        'date_naissance_jour' => '12', 'date_naissance_mois' => '3', 'date_naissance_annee' => (string) (now()->year - 9),
        'photo' => UploadedFile::fake()->image('awa.jpg', 300, 400),
        ...$autres,
    ];
}

it('laisse un membre inscrire son enfant, puis payer ses frais de carte et voir sa carte', function () {
    Notification::fake();

    $this->actingAs($this->parent)->post(route('famille.store'), ficheDeProche())->assertRedirect();

    $awa = Membre::query()->where('prenom', 'Awa')->firstOrFail();
    expect($awa->statut)->toBe(StatutMembre::EnAttente)
        ->and($awa->estEnfant())->toBeTrue()
        ->and($awa->telephone)->toBeNull()
        ->and($awa->user_id)->toBeNull()
        ->and($awa->cellule_id)->toBe($this->cellule->id)
        ->and($awa->responsable_id)->toBe($this->moussa->id)
        ->and($awa->lien_famille)->toBe(LienFamille::Enfant)
        ->and($awa->inscrit_par)->toBe($this->parent->id);

    $this->actingAs($this->parent)->get(route('famille.index'))->assertOk()->assertSee('Awa')->assertSee('En attente');

    // Le Jewrine de cellule accepte la fiche ; le parent est prévenu pour elle.
    app(StatutMembreService::class)->changer($awa, StatutMembre::Accepte, $this->jewrine);
    Notification::assertSentTo($this->parent, NotificationMembre::class, fn (NotificationMembre $avis): bool => str_starts_with($avis->toArray($this->parent)['titre'], 'Awa : '));

    $this->actingAs($this->parent)->post(route('famille.frais', $awa), ['reference' => 'T_AWA123456789AB'])->assertRedirect(route('famille.show', $awa));
    $frais = Paiement::query()->where('membre_id', $awa->id)->where('objet', 'frais_carte')->firstOrFail();
    expect($frais->declare_par)->toBe($this->parent->id);

    app(PaiementService::class)->confirmer($frais, $this->tresorier);
    $awa->refresh();

    expect($awa->statut)->toBe(StatutMembre::Valide)->and($awa->matricule)->not->toBeNull();
    Notification::assertSentTo($this->parent, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->parent)['lien'] === '/ma-famille/'.$awa->id);

    $this->actingAs($this->parent)->get(route('famille.show', $awa))->assertOk()->assertSee('Sa carte')->assertSee('Ses reçus');
    $this->actingAs($this->parent)->get(route('famille.carte', $awa))->assertOk()->assertSee('Carte de Awa')->assertSee('Contact (famille)')->assertSee('+221 77 111 22 33');
    $this->actingAs($this->parent)->get(route('famille.carte.pdf', $awa))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($this->parent)->get(route('recus.show', $frais))->assertOk();
    $this->actingAs($this->parent)->get(route('photos.show', $awa))->assertOk();

    // Elle cotise comme tout membre : le parent paie pour elle.
    $this->actingAs($this->parent)->get(route('famille.cotisations', $awa))->assertOk()->assertSee('Cotisations de Awa')->assertSee('Payer ses cotisations');
    $this->actingAs($this->parent)->post(route('famille.cotisations.declarer', $awa), ['reference' => 'T_AWA987654321CD', 'nombre_mois' => 2])
        ->assertRedirect(route('famille.cotisations', $awa));
    $cotisation = Paiement::query()->where('membre_id', $awa->id)->where('objet', 'cotisation')->firstOrFail();
    expect($cotisation->montant)->toBe(2000)->and($cotisation->declare_par)->toBe($this->parent->id);
});

it('ne montre la fiche d\'un proche qu\'au membre qui la gère', function () {
    $awa = $this->familles->ajouterProche($this->moussa, [
        'prenom' => 'Awa', 'nom' => 'Ndiaye', 'sexe' => 'F', 'date_naissance' => now()->subYears(8)->toDateString(), 'lien_famille' => 'enfant',
    ], UploadedFile::fake()->image('awa.jpg'));
    $voisin = User::factory()->create();
    $voisin->assignRole('membre');
    Membre::factory()->for($voisin)->valide()->create(['cellule_id' => $this->cellule->id]);

    foreach (['famille.show', 'famille.carte', 'famille.cotisations'] as $route) {
        $this->actingAs($voisin)->get(route($route, $awa))->assertForbidden();
    }

    $this->actingAs($voisin)->post(route('famille.frais', $awa), ['reference' => 'T_VOISIN12345678'])->assertForbidden();
    $this->actingAs($voisin)->get(route('photos.show', $awa))->assertForbidden();
});

it('refuse un téléphone pour un enfant, et un proche ajouté par un membre pas encore validé', function () {
    $this->actingAs($this->parent)->post(route('famille.store'), ficheDeProche(['telephone' => '77 999 88 77']))
        ->assertSessionHasErrors(['telephone' => 'Un enfant de moins de 12 ans n\'a pas de téléphone à lui : laissez ce champ vide.']);
    $this->actingAs($this->parent)->post(route('famille.store'), ficheDeProche(['telephone' => '77 111 22 33', 'date_naissance_annee' => '1950', 'lien_famille' => 'parent']))
        ->assertSessionHasErrors(['telephone' => 'Ce numéro est déjà utilisé par un autre membre.']);

    $nouveau = User::factory()->create();
    $nouveau->assignRole('membre');
    $enAttente = Membre::factory()->for($nouveau)->create(['cellule_id' => $this->cellule->id]);
    expect(fn () => $this->familles->ajouterProche($enAttente, ['prenom' => 'Bébé', 'nom' => 'Sy', 'sexe' => 'H', 'date_naissance' => now()->subYear()->toDateString(), 'lien_famille' => 'enfant'], UploadedFile::fake()->image('b.jpg')))
        ->toThrow(DomainException::class, 'doit être validée');

    expect(Membre::query()->where('nom', 'NDIAYE')->exists())->toBeFalse();
});

it('accepte un adulte sans téléphone, dont la fiche est gérée par un proche', function () {
    $this->actingAs($this->parent)->post(route('famille.store'), ficheDeProche(['prenom' => 'Mame Diarra', 'date_naissance_annee' => '1948', 'lien_famille' => 'parent']))
        ->assertRedirect();

    $mame = Membre::query()->where('prenom', 'Mame Diarra')->firstOrFail();
    expect($mame->estEnfant())->toBeFalse()->and($mame->telephone)->toBeNull()->and($mame->lien_famille)->toBe(LienFamille::Parent);
});

it('ne laisse personne valider la fiche ou le paiement d\'un proche dont il gère la fiche', function () {
    $fils = $this->familles->ajouterProche($this->moussa, [
        'prenom' => 'Ibrahima', 'nom' => 'Ndiaye', 'sexe' => 'H', 'date_naissance' => now()->subYears(10)->toDateString(), 'lien_famille' => 'enfant',
    ], UploadedFile::fake()->image('i.jpg'));

    // Le parent est aussi Jewrine de la cellule et Jewrine finance : il ne valide ni la fiche ni le paiement.
    nommerPourLeTest($this->parent, 'jewrine_cellule', $this->cellule);
    $this->parent->assignRole('jewrine_finance');

    expect($this->parent->fresh()->can('changerStatut', [$fils, StatutMembre::Accepte]))->toBeFalse()
        ->and($this->jewrine->can('changerStatut', [$fils, StatutMembre::Accepte]))->toBeTrue();

    app(StatutMembreService::class)->changer($fils, StatutMembre::Accepte, $this->jewrine);
    $frais = app(PaiementService::class)->declarer($fils->fresh(), 'T_IBRA1234567890', null, $this->parent);

    expect(fn () => app(PaiementService::class)->confirmer($frais, $this->parent->fresh()))->toThrow(DomainException::class, 'Vous gérez la fiche de ce proche')
        ->and($frais->fresh()->statut)->toBe(StatutPaiement::Declare);
});

it('laisse le Jewrine inscrire un membre sans téléphone, et rattacher un enfant à son parent', function () {
    $this->actingAs($this->jewrine);

    Livewire::test(CreateMembre::class)
        ->fillForm(['prenom' => 'Ndèye', 'nom' => 'Fall', 'sexe' => 'F', 'date_naissance' => '1960-05-01', 'cellule_id' => $this->cellule->id, 'pays_residence' => 'Sénégal'])
        ->call('create')
        ->assertHasNoFormErrors();

    $ndeye = Membre::query()->where('prenom', 'Ndèye')->firstOrFail();
    expect($ndeye->telephone)->toBeNull()
        ->and($ndeye->statut)->toBe(StatutMembre::Accepte)
        ->and($ndeye->inscrit_par)->toBe($this->jewrine->id);

    // Un enfant sans parent est refusé ; avec son parent, il prend sa cellule.
    $autreCellule = Cellule::factory()->create();
    Livewire::test(CreateMembre::class)
        ->fillForm(['prenom' => 'Cheikh', 'nom' => 'Ndiaye', 'sexe' => 'H', 'date_naissance' => now()->subYears(6)->toDateString(), 'cellule_id' => $this->cellule->id, 'pays_residence' => 'Sénégal'])
        ->call('create')
        ->assertNotified('Un enfant de moins de 12 ans, ou un jeune de moins de 18 ans sans son propre téléphone, est rattaché à un parent ou tuteur, membre de la Daara.');

    Livewire::test(CreateMembre::class)
        ->fillForm(['prenom' => 'Cheikh', 'nom' => 'Ndiaye', 'sexe' => 'H', 'date_naissance' => now()->subYears(6)->toDateString(), 'cellule_id' => $this->cellule->id, 'pays_residence' => 'Sénégal', 'responsable_id' => $this->moussa->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $cheikh = Membre::query()->where('prenom', 'Cheikh')->firstOrFail();
    expect($cheikh->responsable_id)->toBe($this->moussa->id)->and($cheikh->lien_famille)->toBe(LienFamille::Enfant);

    // Rattaché à un parent d'une autre cellule, l'enfant le suit.
    $parentAilleurs = Membre::factory()->for(User::factory())->valide()->create(['cellule_id' => $autreCellule->id]);
    expect($this->familles->rattacher(['responsable_id' => $parentAilleurs->id, 'cellule_id' => $this->cellule->id], $cheikh)['cellule_id'])->toBe($autreCellule->id)
        ->and(fn () => $this->familles->rattacher(['responsable_id' => $cheikh->id], $ndeye))->toThrow(DomainException::class, 'membre validé');

    $voisin = Membre::factory()->for(User::factory())->valide()->create(['cellule_id' => $this->cellule->id]);
    Livewire::test(EditMembre::class, ['record' => $this->moussa->getRouteKey()])
        ->fillForm(['responsable_id' => $voisin->id])
        ->call('save')
        ->assertNotified('Un membre qui a son propre compte ne se rattache pas à un proche.');
    expect($this->moussa->fresh()->responsable_id)->toBeNull();
});

it('propose à 18 ans, une seule fois, de donner son propre compte au proche, qui garde tout son historique', function () {
    Notification::fake();
    $awa = $this->familles->ajouterProche($this->moussa, [
        'prenom' => 'Awa', 'nom' => 'Ndiaye', 'sexe' => 'F', 'date_naissance' => now()->subYears(18)->addDays(2)->toDateString(), 'lien_famille' => 'enfant',
    ], UploadedFile::fake()->image('awa.jpg'));
    app(StatutMembreService::class)->changer($awa, StatutMembre::Accepte, $this->jewrine);
    app(PaiementService::class)->confirmer(app(PaiementService::class)->declarer($awa->fresh(), 'T_AWA123456789AB', null, $this->parent), $this->tresorier);

    // Un enfant de moins de 12 ans ne reçoit pas de compte.
    $petit = $this->familles->ajouterProche($this->moussa, [
        'prenom' => 'Fallou', 'nom' => 'Ndiaye', 'sexe' => 'H', 'date_naissance' => now()->subYears(12)->addDays(2)->toDateString(), 'lien_famille' => 'enfant',
    ], UploadedFile::fake()->image('fallou.jpg'));
    expect($this->familles->proposerComptesAuxMajeurs())->toBe(0)
        ->and(fn () => $this->familles->donnerSonCompte($petit, '77 444 55 66', $this->parent))->toThrow(DomainException::class, 'moins de 12 ans');

    $this->travel(3)->days();
    expect($this->familles->proposerComptesAuxMajeurs())->toBe(1)
        ->and($this->familles->proposerComptesAuxMajeurs())->toBe(0);
    Notification::assertSentTo($this->parent, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->parent)['titre'] === 'Famille : Awa a 18 ans');
    Notification::assertSentTo($this->jewrine, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->jewrine)['titre'] === 'Cellule : Awa a 18 ans');

    $this->actingAs($this->parent)->get(route('famille.show', $awa))->assertSee('Lui donner son propre compte');
    $this->actingAs($this->parent)->post(route('famille.compte', $awa), ['telephone' => '77 444 55 66'])
        ->assertRedirect(route('famille.index'))
        ->assertSessionHas('succes', fn (string $message): bool => str_contains($message, 'mot de passe provisoire'));

    $awa->refresh();
    $compte = $awa->user;
    expect($compte)->not->toBeNull()
        ->and($compte->telephone)->toBe('+221774445566')
        ->and($compte->doit_changer_mot_de_passe)->toBeTrue()
        ->and($compte->hasRole('membre'))->toBeTrue()
        ->and($awa->responsable_id)->toBeNull()
        ->and($awa->telephone)->toBe('+221774445566')
        ->and($awa->paiements()->count())->toBe(1)
        ->and($awa->carteActive)->not->toBeNull();

    $this->actingAs($this->parent)->get(route('famille.show', $awa))->assertForbidden();
    $this->artisan('familles:routine')->assertSuccessful();
});

it('laisse le Jewrine de cellule donner son compte à un proche majeur, depuis le panneau', function () {
    Notification::fake();
    $mame = $this->familles->ajouterProche($this->moussa, [
        'prenom' => 'Mame', 'nom' => 'Ndiaye', 'sexe' => 'F', 'date_naissance' => '1950-01-01', 'lien_famille' => 'parent',
    ], UploadedFile::fake()->image('mame.jpg'));

    $this->actingAs($this->jewrine);
    Livewire::test(EditMembre::class, ['record' => $mame->getRouteKey()])
        ->assertActionVisible('donnerSonCompte')
        ->callAction('donnerSonCompte', ['telephone' => '76 555 44 33'])
        ->assertHasNoActionErrors();

    expect($mame->fresh()->user_id)->not->toBeNull();
    Notification::assertSentTo($this->parent, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($this->parent)['titre'] === 'Famille : Mame a son compte');
});
