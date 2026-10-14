<?php

use App\Enums\ModeCollecte;
use App\Enums\ProgrammeCollecte;
use App\Enums\StatutCollecte;
use App\Models\Collecte;
use App\Models\Membre;
use App\Models\Parametre;
use App\Models\User;
use App\Services\CollecteService;
use App\Services\PaiementService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Parametre::query()->updateOrCreate(['cle' => 'wave_lien_frais_carte'], ['valeur' => 'https://pay.wave.com/m/DAARA/c/sn/?amount=1000', 'libelle' => 'Lien Wave']);

    $this->compte = User::factory()->create();
    $this->compte->assignRole('membre');
    $this->membre = Membre::factory()->for($this->compte)->valide()->create();

    $this->collecte = Collecte::query()->create([
        'titre' => 'Magal de Touba 2026',
        'programme' => ProgrammeCollecte::Magal,
        'mode' => ModeCollecte::EngagementLibre,
        'ouverture' => now()->subDay(),
        'statut' => StatutCollecte::Ouverte,
    ]);

    $this->service = app(CollecteService::class);
    $this->service->fixerParMembre($this->collecte, $this->membre, 20000);
});

it('propose des boutons rapides et une case libre pour verser petit à petit', function () {
    $this->actingAs($this->compte)->get(route('engagements.index'))
        ->assertOk()
        ->assertSee('Combien voulez-vous verser aujourd')
        ->assertSee('data-montant="2000"', false)
        ->assertSee('data-montant="10000"', false)
        ->assertSee('Tout le reste (20 000 FCFA)')
        ->assertSee('Ou écrivez le montant de votre choix')
        ->assertSee('amount=20000', false);
});

it('accepte plusieurs versements successifs, sans jamais dépasser le reste', function () {
    $this->actingAs($this->compte)->post(route('engagements.versement', $this->collecte), ['montant' => 5000, 'reference' => 'T_PETITAPETIT00001'])
        ->assertRedirect(route('engagements.index'));
    $this->actingAs($this->compte)->post(route('engagements.versement', $this->collecte), ['montant' => 3500, 'reference' => 'T_PETITAPETIT00002'])
        ->assertRedirect(route('engagements.index'));

    expect($this->service->enVerification($this->collecte, $this->membre))->toBe(8500);

    $this->actingAs($this->compte)->post(route('engagements.versement', $this->collecte), ['montant' => 15000, 'reference' => 'T_PETITAPETIT00003'])
        ->assertSessionHasErrors('montant');

    $this->actingAs($this->compte)->get(route('engagements.index'))
        ->assertSee('8 500 FCFA en vérification')
        ->assertSee('Tout le reste (11 500 FCFA)');
});

it('fait avancer la jauge quand le Jewrine finance confirme', function () {
    $tresorier = User::factory()->create();
    $tresorier->assignRole('jewrine_finance');

    $paiement = $this->service->declarer($this->collecte, $this->membre, 5000, 'T_PETITAPETIT00004');
    $engagement = $this->service->engagementDe($this->collecte, $this->membre);

    expect($engagement?->verse())->toBe(0);

    app(PaiementService::class)->confirmer($paiement, $tresorier);

    expect($engagement?->verse())->toBe(5000)
        ->and($engagement?->taux())->toBe(25)
        ->and($this->service->enVerification($this->collecte, $this->membre))->toBe(0);

    $this->actingAs($this->compte)->get(route('engagements.index'))
        ->assertSee('aria-valuenow="25"', false)
        ->assertSee('Reste à verser');
});
