<?php

use App\Models\Membre;
use App\Models\Parametre;
use App\Models\User;
use App\Services\CotisationService;
use App\Services\PaiementService;
use App\Support\LienWave;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, ParametresSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');

    $this->user = User::factory()->create();
    $this->user->assignRole('membre');
    $this->membre = Membre::factory()->for($this->user)->valide()->create([
        'valide_le' => now()->startOfMonth()->subMonth(),
        'telephone' => '+221775556677',
    ]);
});

it('refuse une référence déjà utilisée par un autre membre', function () {
    $autre = Membre::factory()->valide()->create(['valide_le' => now()->subMonth()]);
    app(CotisationService::class)->declarer($autre, 'TWAVE12345', 1);

    $this->actingAs($this->user)->post('/mes-cotisations', ['reference' => 'twave 12345', 'nombre_mois' => 1])
        ->assertSessionHasErrors('reference');
});

it('refuse une référence mal formée', function () {
    $this->actingAs($this->user)->post('/mes-cotisations', ['reference' => 'ABC<script>', 'nombre_mois' => 1])
        ->assertSessionHasErrors('reference');
});

it('enregistre le numéro Wave utilisé pour payer', function () {
    $this->actingAs($this->user)->post('/mes-cotisations', [
        'reference' => 'T_WAVE777TEST001',
        'nombre_mois' => 2,
        'numero_wave' => '76 111 22 33',
    ])->assertRedirect(route('cotisations'));

    expect(DB::table('paiements')->value('numero_wave'))->toBe('+221761112233');
});

it('refuse un numéro Wave invalide', function () {
    $this->actingAs($this->user)->post('/mes-cotisations', [
        'reference' => 'T_WAVE888TEST001',
        'nombre_mois' => 1,
        'numero_wave' => '123',
    ])->assertSessionHasErrors('numero_wave');
});

it('interdit au Jewrine finance de confirmer son propre paiement', function () {
    $this->user->assignRole('jewrine_finance');
    $paiement = app(CotisationService::class)->declarer($this->membre, 'TMOIMEME1', 1);

    app(PaiementService::class)->confirmer($paiement, $this->user);
})->throws(DomainException::class);

it('interdit au Jewrine finance d\'enregistrer ses propres espèces', function () {
    $this->user->assignRole('jewrine_finance');

    app(CotisationService::class)->enregistrerEspeces($this->membre, 1, $this->user);
})->throws(DomainException::class);

it('alerte le super administrateur après 3 déclarations refusées', function () {
    $paiements = app(PaiementService::class);
    $cotisations = app(CotisationService::class);

    foreach (['TFAUX001', 'TFAUX002', 'TFAUX003'] as $reference) {
        $paiements->refuser($cotisations->declarer($this->membre, $reference, 1), $this->tresorier, 'Introuvable');
    }

    expect(DB::table('notifications')->where('notifiable_id', $this->super->id)->count())->toBe(1);
});

it('utilise en priorité les liens Wave à montant fixe', function () {
    Parametre::query()->where('cle', 'wave_lien_2_mois')->update(['valeur' => 'https://pay.wave.com/m/DEUXMOIS']);
    Parametre::query()->where('cle', 'wave_lien_paiement')->update(['valeur' => 'https://pay.wave.com/m/GENERAL']);

    expect(LienWave::cotisation(2, 1000))->toBe('https://pay.wave.com/m/DEUXMOIS')
        ->and(LienWave::cotisation(3, 1000))->toBe('https://pay.wave.com/m/GENERAL?amount=3000');
});
