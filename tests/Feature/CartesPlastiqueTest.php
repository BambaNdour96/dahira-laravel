<?php

use App\Enums\StatutMembre;
use App\Enums\TypeTarif;
use App\Filament\Resources\LotsCartes\LotCartesResource;
use App\Filament\Resources\LotsCartes\Pages\ListLotsCartes;
use App\Filament\Resources\Membres\Pages\ListMembres;
use App\Models\CartePhysique;
use App\Models\Cellule;
use App\Models\Exercice;
use App\Models\JournalExport;
use App\Models\LotCartes;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Tarif;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CartePhysiqueService;
use App\Services\CarteService;
use App\Services\FamilleService;
use App\Services\PaiementService;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Un membre validé, avec son compte et sa carte. */
function membreCartePlastique(string $prenom, Cellule $cellule): Membre
{
    $compte = User::factory()->create(['name' => $prenom.' Sarr']);
    $compte->assignRole('membre');
    $membre = Membre::factory()->for($compte)->valide()->create(['prenom' => $prenom, 'cellule_id' => $cellule->id, 'valide_le' => now()->subYear()]);
    $membre->forceFill(['matricule' => 'NDK-'.str_pad((string) $membre->id, 4, '0', STR_PAD_LEFT)])->save();
    app(CarteService::class)->emettre($membre);

    return $membre;
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class, PostesSeeder::class]);
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->jewrine = User::factory()->create();
    $this->medina = celluleDirigeePar($this->jewrine, ['nom' => 'Médina']);
    $this->sam = Cellule::factory()->create(['nom' => 'Sam']);
    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');
    $this->secretaire = nommerPourLeTest(User::factory()->create(), 'secretaire_general');

    $this->awa = membreCartePlastique('Awa', $this->medina);
    $this->service = app(CartePhysiqueService::class);
});

function fixerTarifPlastique(int $montant): void
{
    Tarif::query()->create(['type' => TypeTarif::CartePhysique, 'libelle' => 'Carte en plastique', 'montant' => $montant, 'actif' => true]);
}

it('affiche « Bientôt disponible » tant que le tarif de la carte en plastique n\'est pas fixé', function () {
    $this->actingAs($this->awa->user)->get(route('carte'))->assertOk()->assertSee('Carte en plastique')->assertSee('Bientôt disponible');
    $this->actingAs($this->awa->user)->post(route('carte.plastique'), ['reference' => 'T_AWA00000000PL1'])
        ->assertSessionHasErrors(['reference' => 'La carte en plastique sera bientôt disponible.']);
});

it('fait commander et payer la carte, puis l\'imprimer par lot, la recevoir et la remettre', function () {
    Notification::fake();
    fixerTarifPlastique(2500);
    $compte = $this->awa->user;

    $this->actingAs($compte)->get(route('carte'))->assertSee('2 500 FCFA')->assertSee('Commander et payer');
    $this->actingAs($compte)->post(route('carte.plastique'), ['reference' => 'T_AWA00000000PL1'])->assertRedirect(route('carte'));
    $this->actingAs($compte)->post(route('carte.plastique'), ['reference' => 'T_AWA00000000PL2'])->assertSessionHasErrors(['reference' => 'Une carte en plastique est déjà commandée.']);
    $this->actingAs($compte)->get(route('carte'))->assertSee('en cours de vérification');

    $paiement = Paiement::query()->where('objet', 'carte_physique')->firstOrFail();
    expect($paiement->montant)->toBe(2500);
    app(PaiementService::class)->confirmer($paiement, $this->tresorier);
    expect($paiement->fresh()->numero_recu)->toStartWith('CPH-');
    $this->actingAs($compte)->get(route('carte'))->assertSee('Payée, en attente d\'impression');

    // Une autre carte payée, dans une autre cellule, reste hors du lot de Médina.
    $ibou = membreCartePlastique('Ibou', $this->sam);
    $this->actingAs($this->tresorier);
    Livewire::test(ListMembres::class)->callAction(TestAction::make('cartePhysiqueEspeces')->table($ibou))->assertHasNoActionErrors();
    expect($this->service->enAttente())->toBe(2);

    $this->actingAs($this->secretaire);
    expect(LotCartesResource::canViewAny())->toBeTrue();
    Livewire::test(ListLotsCartes::class)->callAction('preparer', ['cellule_id' => $this->medina->id])->assertHasNoActionErrors();

    $lot = LotCartes::query()->firstOrFail();
    $commande = CartePhysique::query()->where('membre_id', $this->awa->id)->firstOrFail();
    expect($lot->numero)->toBe('LOT-'.now()->format('Y').'-001')
        ->and($lot->cartes()->count())->toBe(1)
        ->and($commande->statut)->toBe(CartePhysique::EN_LOT)
        ->and($commande->carte_id)->toBe($this->awa->carteActive()->value('id'))
        ->and($this->service->enAttente())->toBe(1);

    $this->get(route('lots-cartes.fichier', [$lot, 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $csv = $this->get(route('lots-cartes.fichier', [$lot, 'csv']))->assertOk()->getContent();
    expect((string) $csv)->toContain($this->awa->matricule)->toContain('Médina')->not->toContain('Ibou')
        ->and(JournalExport::query()->where('document', 'like', 'Lot de cartes%')->exists())->toBeTrue();

    Livewire::test(ListLotsCartes::class)
        ->callAction(TestAction::make('envoyer')->table($lot))
        ->callAction(TestAction::make('recevoir')->table($lot->fresh()))
        ->assertHasNoActionErrors();
    expect($commande->fresh()->statut)->toBe(CartePhysique::PRETE);
    Notification::assertSentTo($compte, NotificationMembre::class, fn (NotificationMembre $avis): bool => $avis->toArray($compte)['titre'] === 'Carte en plastique arrivée');

    $this->actingAs($this->jewrine);
    Livewire::test(ListMembres::class)->callAction(TestAction::make('remettreCartePhysique')->table($this->awa))->assertHasNoActionErrors();
    expect($commande->fresh()->statut)->toBe(CartePhysique::REMISE)->and($commande->fresh()->remise_par)->toBe($this->jewrine->id);

    // Un nouveau lot de Médina : plus rien à imprimer.
    expect(fn () => $this->service->preparerLot($this->medina, $this->secretaire))->toThrow(DomainException::class, 'Aucune carte payée');
});

it('réserve les lots au super administrateur et au Secrétaire général', function () {
    $this->actingAs($this->tresorier);
    expect(LotCartesResource::canViewAny())->toBeFalse()
        ->and(fn () => $this->service->preparerLot(null, $this->tresorier))->toThrow(DomainException::class, 'Seuls le super administrateur et le Secrétaire général');

    fixerTarifPlastique(2000);
    app(PaiementService::class)->confirmer($this->service->commander($this->awa, 'T_AWA00000000PL3', null, $this->awa->user), $this->tresorier);
    $lot = $this->service->preparerLot(null, $this->secretaire);
    $this->actingAs($this->tresorier)->get(route('lots-cartes.fichier', [$lot, 'pdf']))->assertForbidden();
});

it('laisse le parent commander la carte en plastique d\'un proche', function () {
    fixerTarifPlastique(2000);
    $enfant = app(FamilleService::class)->ajouterProche($this->awa, [
        'prenom' => 'Fallou', 'nom' => 'Sarr', 'sexe' => 'H', 'date_naissance' => now()->subYears(8)->toDateString(), 'lien_famille' => 'enfant',
    ], UploadedFile::fake()->image('f.jpg'));
    $enfant->forceFill(['statut' => StatutMembre::Valide])->save();
    app(CarteService::class)->emettre($enfant);

    $this->actingAs($this->awa->user)->get(route('famille.carte', $enfant))->assertSee('Commander et payer');
    $this->actingAs($this->awa->user)->post(route('famille.carte.plastique', $enfant), ['reference' => 'T_FALLOU0000PL01'])->assertRedirect(route('famille.carte', $enfant));
    expect(Paiement::query()->where('membre_id', $enfant->id)->where('objet', 'carte_physique')->value('declare_par'))->toBe($this->awa->user->id);
});

it('indique l\'année de la carte sur la page de vérification', function () {
    $exercice = Exercice::query()->create(['libelle' => '2027', 'date_debut' => '2027-01-01', 'date_fin' => '2027-12-31']);
    $carte = $this->awa->carteActive()->firstOrFail();
    $carte->update(['exercice_id' => $exercice->id]);

    $this->get(route('verification', $carte->jeton))->assertOk()->assertSee('Carte 2027');
    $this->actingAs($this->awa->user)->get(route('carte'))->assertSee('Carte 2027');
    $this->actingAs($this->awa->user)->get(route('carte.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('met 10 cartes par page A4 dans le fichier de l\'imprimeur', function () {
    fixerTarifPlastique(2000);

    for ($i = 1; $i <= 11; $i++) {
        $membre = membreCartePlastique('Membre'.$i, $this->medina);
        $membre->update(['photo_path' => UploadedFile::fake()->image('p.jpg', 300, 400)->store('photos', 'local')]);
        app(PaiementService::class)->confirmer($this->service->commander($membre, 'T_PLANCHE00000'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), null, $membre->user), $this->tresorier);
    }

    $pdf = $this->service->pdf($this->service->preparerLot(null, $this->secretaire));

    expect(preg_match_all('#/Type\s*/Page[^s]#', $pdf))->toBe(2);
});
