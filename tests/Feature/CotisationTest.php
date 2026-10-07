<?php

use App\Models\Cotisation;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\User;
use App\Services\CarteService;
use App\Services\CotisationService;
use App\Services\PaiementService;
use App\Support\LienWave;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);

    // Dans les tests, les cotisations peuvent remonter loin dans le passé.
    config(['dahira.cotisations_debut' => '2000-01']);

    $this->service = app(CotisationService::class);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('tresorier');

    $this->user = User::factory()->create();
    $this->user->assignRole('membre');
});

function membreValideDepuis(User $user, int $moisEcoules): Membre
{
    return Membre::factory()->for($user)->valide()->create([
        'valide_le' => now()->startOfMonth()->subMonths($moisEcoules),
    ]);
}

it('fait commencer les cotisations le mois suivant la validation', function () {
    $membre = membreValideDepuis($this->user, 0);

    expect($this->service->debut($membre)?->format('Y-m'))->toBe(now()->startOfMonth()->addMonth()->format('Y-m'))
        ->and($this->service->moisEnRetard($membre))->toBe([]);
});

it('compte les mois passés impayés', function () {
    $membre = membreValideDepuis($this->user, 3);

    expect($this->service->moisEnRetard($membre))->toHaveCount(2);
});

it('ne réclame rien avant la date de démarrage des cotisations', function () {
    config(['dahira.cotisations_debut' => now()->format('Y-m')]);
    $membre = membreValideDepuis($this->user, 24);

    expect($this->service->moisEnRetard($membre))->toBe([]);
});

it('enregistre une déclaration de plusieurs mois', function () {
    membreValideDepuis($this->user, 0);

    $this->actingAs($this->user)->post('/mes-cotisations', ['reference' => 't_cot 123', 'nombre_mois' => 3])
        ->assertRedirect(route('cotisations'));

    $paiement = Paiement::query()->firstOrFail();

    expect($paiement->objet)->toBe('cotisation')
        ->and($paiement->montant)->toBe(3000)
        ->and($paiement->nombre_mois)->toBe(3)
        ->and($paiement->reference)->toBe('T_COT123');
});

it('couvre d\'abord les mois de retard à la confirmation', function () {
    $membre = membreValideDepuis($this->user, 3);

    $paiement = $this->service->declarer($membre, 'T_COT1', 3);
    app(PaiementService::class)->confirmer($paiement, $this->tresorier);

    expect($this->service->moisEnRetard($membre))->toBe([])
        ->and(Cotisation::query()->count())->toBe(3)
        ->and($this->service->regleJusqua($membre)?->format('Y-m'))->toBe(now()->format('Y-m'));
});

it('enregistre une cotisation en espèces', function () {
    $membre = membreValideDepuis($this->user, 2);

    $this->service->enregistrerEspeces($membre, 1, $this->tresorier);

    expect($this->service->moisEnRetard($membre))->toBe([]);
});

it('refuse une deuxième déclaration en cours', function () {
    $membre = membreValideDepuis($this->user, 0);

    $this->service->declarer($membre, 'T_UN', 1);
    $this->service->declarer($membre, 'T_DEUX', 1);
})->throws(DomainException::class);

it('refuse les cotisations d\'un membre non validé', function () {
    $membre = Membre::factory()->for($this->user)->create();

    $this->service->declarer($membre, 'T_123456', 1);
})->throws(DomainException::class);

it('affiche le retard au scan de la carte', function () {
    $enRetard = membreValideDepuis($this->user, 3);
    $aJour = membreValideDepuis(User::factory()->create(), 0);

    $this->get('/v/'.app(CarteService::class)->emettre($enRetard)->jeton)
        ->assertSee('Membre actif')
        ->assertSee('Cotisation en retard : 2 mois');

    $this->get('/v/'.app(CarteService::class)->emettre($aJour)->jeton)
        ->assertSee('Membre actif')
        ->assertSee('Cotisation à jour');
});

it('réserve la page des cotisations aux membres validés', function () {
    Membre::factory()->for($this->user)->create();
    $this->actingAs($this->user)->get('/mes-cotisations')->assertNotFound();

    $valide = User::factory()->create();
    $valide->assignRole('membre');
    membreValideDepuis($valide, 1);

    $this->actingAs($valide)->get('/mes-cotisations')->assertOk()->assertSee('1 000 FCFA');
});

it('propose un lien Wave différent pour 1, 2 et 3 mois', function () {
    foreach ([1 => 'UNMOIS', 2 => 'DEUXMOIS', 3 => 'TROISMOIS'] as $nombre => $code) {
        Parametre::query()->create([
            'cle' => 'wave_lien_'.$nombre.'_mois',
            'libelle' => 'Lien '.$nombre.' mois',
            'valeur' => 'https://pay.wave.com/m/'.$code,
        ]);
    }

    membreValideDepuis($this->user, 1);

    $this->actingAs($this->user)->get('/mes-cotisations')
        ->assertOk()
        ->assertSee('https://pay.wave.com/m/UNMOIS')
        ->assertSee('https://pay.wave.com/m/DEUXMOIS')
        ->assertSee('https://pay.wave.com/m/TROISMOIS');
});

it('n\'ouvre jamais le lien d\'un autre montant quand un lien manque', function () {
    Parametre::query()->create([
        'cle' => 'wave_lien_1_mois',
        'libelle' => 'Lien 1 mois',
        'valeur' => 'https://pay.wave.com/m/UNMOIS',
    ]);

    membreValideDepuis($this->user, 1);

    expect(LienWave::cotisation(2, 1000))->toBeNull();
});
