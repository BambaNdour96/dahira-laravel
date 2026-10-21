<?php

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Filament\Resources\Collectes\Pages\ListCollectes;
use App\Filament\Resources\Collectes\Pages\ViewCollecte;
use App\Filament\Widgets\CollectesEnCours;
use App\Models\Collecte;
use App\Models\HistoriqueEngagement;
use App\Models\Membre;
use App\Models\Parametre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CollecteService;
use App\Services\PaiementService;
use Database\Seeders\RolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function nouvelleCollecte(ModeCollecte $mode, array $attributs = []): Collecte
{
    return Collecte::query()->create(array_merge([
        'titre' => 'Magal de Touba 2026',
        'type_programme_id' => typeProgramme('magal'),
        'mode' => $mode,
        'ouverture' => now()->subDay(),
        'date_limite' => now()->addMonth(),
        'statut' => StatutCollecte::Ouverte,
    ], $attributs));
}

function membreDuBureau(string $role): User
{
    $compte = User::factory()->create();
    $compte->assignRole('membre');
    $membre = Membre::factory()->for($compte)->valide()->create();

    return nommerPourLeTest($compte, $role);
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->service = app(CollecteService::class);

    $this->compte = User::factory()->create(['telephone' => '+221770001111']);
    $this->compte->assignRole('membre');
    $this->membre = Membre::factory()->for($this->compte)->valide()->create();

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
});

it('laisse le membre fixer son engagement une seule fois, puis le bureau le modifie avec une trace', function () {
    Notification::fake();
    $collecte = nouvelleCollecte(ModeCollecte::Jayante);

    $engagement = $this->service->fixerParMembre($collecte, $this->membre, 25000);

    expect($engagement->montant)->toBe(25000)
        ->and(fn () => $this->service->fixerParMembre($collecte, $this->membre, 10000))
        ->toThrow(DomainException::class, 'adressez-vous au bureau');

    $tresorier = membreDuBureau('jewrine_finance');
    $this->service->modifier($engagement, 15000, 'Situation familiale', $tresorier);

    $trace = HistoriqueEngagement::query()->latest('id')->first();
    expect($engagement->fresh()->montant)->toBe(15000)
        ->and($trace->ancien)->toBe(25000)
        ->and($trace->nouveau)->toBe(15000)
        ->and($trace->motif)->toBe('Situation familiale')
        ->and($trace->auteur_id)->toBe($tresorier->id)
        ->and(fn () => $this->service->modifier($engagement, 9000, ' ', $tresorier))->toThrow(DomainException::class);

    Notification::assertSentTo($this->compte, NotificationMembre::class);
});

it('engage d\'office au Sass du Prokhane, laisse l\'augmenter et jamais descendre en dessous', function () {
    $collecte = nouvelleCollecte(ModeCollecte::Sass, ['titre' => 'Prokhane 2026', 'type_programme_id' => typeProgramme('prokhane'), 'montant' => 5000]);

    $engagement = $this->service->engagementDe($collecte, $this->membre);
    expect($engagement?->montant)->toBe(5000)
        ->and($engagement?->fixe_par)->toBe('auto')
        ->and($engagement?->nature())->toBe('Sass')
        ->and($this->service->peutFixer($collecte, $this->membre))->toBeFalse()
        ->and(fn () => $this->service->fixerParMembre($collecte, $this->membre, 7500))->toThrow(DomainException::class, 'augmenter')
        ->and(fn () => $this->service->augmenter($engagement, 4000, $this->compte))->toThrow(DomainException::class, 'dépasser');

    $this->service->augmenter($engagement, 7500, $this->compte);
    expect($engagement->fresh()->montant)->toBe(7500)
        ->and(HistoriqueEngagement::query()->latest('id')->value('motif'))->toBe('Sass augmenté par le membre')
        ->and(fn () => $this->service->modifier($engagement->fresh(), 3000, 'Erreur', membreDuBureau('jewrine_finance')))->toThrow(DomainException::class, 'en dessous du Sass');
});

it('suit le Sass quand le Jewrine national le change, sans toucher à ceux qui ont donné davantage', function () {
    $collecte = nouvelleCollecte(ModeCollecte::Sass, ['montant' => 2000]);
    $autre = Membre::factory()->valide()->create();

    expect($this->service->engagerAuSass($collecte, $this->super))->toBe(2);
    $this->service->augmenter($this->service->engagementDe($collecte, $autre), 10000, $this->super);

    $collecte->update(['montant' => 3000]);
    $this->service->engagerAuSass($collecte, $this->super);

    expect($this->service->engagementDe($collecte, $this->membre)?->montant)->toBe(3000)
        ->and($this->service->engagementDe($collecte, $autre)?->montant)->toBe(10000)
        ->and($this->service->engagerAuSass(nouvelleCollecte(ModeCollecte::Jayante), $this->super))->toBe(0);
});

it('ferme la collecte aux membres tant que la contribution n\'est pas réglée', function () {
    $sansMode = nouvelleCollecte(ModeCollecte::Jayante, ['titre' => 'Gamou 2026']);
    $sansMode->forceFill(['mode' => null])->save();
    $sassSansMontant = nouvelleCollecte(ModeCollecte::Sass, ['titre' => 'Ziar 2026']);

    expect($sansMode->fresh()->estOuverte())->toBeFalse()
        ->and($sassSansMontant->estOuverte())->toBeFalse()
        ->and($this->service->engagementDe($sassSansMontant, $this->membre))->toBeNull()
        ->and($this->service->ouvertesPour($this->membre))->toHaveCount(0);
});

it('compte les versements confirmés, avec un reçu EVT, même après la date limite', function () {
    $collecte = nouvelleCollecte(ModeCollecte::Jayante, ['date_limite' => now()->subDays(3)]);
    $engagement = $this->service->fixerParMembre($collecte, $this->membre, 10000);
    $tresorier = membreDuBureau('jewrine_finance');

    expect($collecte->estOuverte())->toBeTrue()
        ->and($engagement->situation())->toBe('En retard');

    $paiement = $this->service->declarer($collecte, $this->membre, 4000, 'T_COLLECTE00000001');
    app(PaiementService::class)->confirmer($paiement, $tresorier);

    expect($paiement->fresh()->numero_recu)->toStartWith('EVT-')
        ->and($paiement->fresh()->natureRecu())->toBe('Reçu de participation')
        ->and($engagement->verse())->toBe(4000)
        ->and($engagement->reste())->toBe(6000);

    $this->service->enregistrerEspeces($collecte, $this->membre, 6000, $tresorier);

    expect($engagement->reste())->toBe(0)
        ->and($engagement->situation())->toBe('À jour');
});

it('répartit les droits, du super administrateur au Jewrine finance', function () {
    $collecte = nouvelleCollecte(ModeCollecte::Jayante);
    $national = membreDuBureau('jewrine_national');
    $secretaire = membreDuBureau('secretaire_general');
    $tresorier = membreDuBureau('jewrine_finance');

    expect($this->super->can('create', Collecte::class))->toBeTrue()
        ->and($national->can('create', Collecte::class))->toBeTrue()
        ->and($national->can('programmes.contribution'))->toBeFalse()
        ->and($this->super->can('programmes.contribution'))->toBeTrue()
        ->and($national->can('cloturer', $collecte))->toBeTrue()
        ->and($secretaire->can('create', Collecte::class))->toBeFalse()
        ->and($secretaire->can('cloturer', $collecte))->toBeFalse()
        ->and($tresorier->can('modifierEngagement', $collecte))->toBeTrue()
        ->and($this->compte->can('viewAny', Collecte::class))->toBeFalse();
});

it('montre au membre ses engagements, avec le bouton Wave du montant restant', function () {
    Parametre::query()->updateOrCreate(['cle' => 'wave_lien_frais_carte'], ['valeur' => 'https://pay.wave.com/m/DAARA/c/sn/?amount=1000', 'libelle' => 'Lien Wave']);
    $collecte = nouvelleCollecte(ModeCollecte::Jayante, ['visible_membres' => true, 'objectif' => 1000000]);

    $this->actingAs($this->compte)->get(route('engagements.index'))
        ->assertOk()
        ->assertSee('Magal de Touba 2026')
        ->assertSee('Fixez votre Jàyante')
        ->assertSee('Avancement de la collecte');

    $this->actingAs($this->compte)->post(route('engagements.fixer', $collecte), ['montant' => 20000])
        ->assertRedirect(route('engagements.index'));

    $this->actingAs($this->compte)->get(route('engagements.index'))
        ->assertSee('amount=20000', false)
        ->assertSee('Reste à verser');

    $this->actingAs($this->compte)->get(route('espace'))->assertSee('Mes Sass et Jàyante');
});

it('fournit la fiche de paramétrage au bureau, en PDF', function () {
    $this->actingAs($this->super)->get(route('collectes.fiche'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($this->compte)->get(route('collectes.fiche'))->assertForbidden();
});

it('ouvre la liste et le tableau de bord d\'une collecte dans le panneau', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $collecte = nouvelleCollecte(ModeCollecte::Jayante, ['objectif' => 500000]);
    $this->service->fixerParMembre($collecte, $this->membre, 10000);

    $this->actingAs($this->super);

    Livewire::test(ListCollectes::class)->assertOk()->assertSee('Magal de Touba 2026');
    Livewire::test(ViewCollecte::class, ['record' => $collecte->getRouteKey()])->assertOk();

    Livewire::test(CollectesEnCours::class)->assertSee('Magal de Touba 2026');
});
