<?php

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Filament\Resources\Paiements\Pages\ManagePaiements;
use App\Models\Membre;
use App\Models\User;
use App\Services\CotisationService;
use App\Support\Indicatifs;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);
});

it('ajoute l\'indicatif choisi, sauf si le numéro en a déjà un', function () {
    expect(Indicatifs::combiner('33', '06 12 34 56 78'))->toBe('+33612345678')
        ->and(Indicatifs::combiner('221', '77 123 45 67'))->toBe('77 123 45 67')
        ->and(Indicatifs::combiner('33', '+221 77 123 45 67'))->toBe('+221 77 123 45 67')
        ->and(Indicatifs::combiner('999', '12345'))->toBe('12345');
});

it('connecte un membre de la diaspora avec le pays choisi et son numéro local', function () {
    $compte = User::factory()->create(['telephone' => '+33612345678', 'password' => 'motdepasse1']);
    $compte->assignRole('membre');

    $this->post('/connexion', ['indicatif' => '33', 'telephone' => '06 12 34 56 78', 'password' => 'motdepasse1']);

    $this->assertAuthenticatedAs($compte);
});

it('propose le choix du pays sur la page de connexion', function () {
    $this->get('/connexion')->assertSee('Pays du numéro')->assertSee('Sénégal (+221)');
});

it('refuse une référence qui n\'a pas le format Wave', function () {
    $compte = User::factory()->create();
    $compte->assignRole('membre');
    Membre::factory()->for($compte)->valide()->create(['valide_le' => now()->subMonth()]);

    $this->actingAs($compte)->post('/mes-cotisations', ['reference' => 'ABC123', 'nombre_mois' => 1])
        ->assertSessionHasErrors('reference');

    $this->actingAs($compte)->post('/mes-cotisations', ['reference' => 't_cn535jqdn4vapi5r', 'nombre_mois' => 1])
        ->assertSessionHasNoErrors();
});

it('ouvre la trésorerie sur les seuls paiements à vérifier', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $tresorier = User::factory()->create();
    $tresorier->assignRole('jewrine_finance');

    $membre = Membre::factory()->valide()->create(['valide_le' => now()->subMonths(2)]);
    $aVerifier = app(CotisationService::class)->declarer($membre, 'T_AVERIFIER00001', 1);
    $deja = app(CotisationService::class)->enregistrerEspeces(Membre::factory()->valide()->create(['valide_le' => now()->subMonths(2)]), 1, $tresorier);

    expect($aVerifier->statut)->toBe(StatutPaiement::Declare);

    $this->actingAs($tresorier);

    Livewire::test(ManagePaiements::class)
        ->assertCanSeeTableRecords([$aVerifier])
        ->assertCanNotSeeTableRecords([$deja]);
});

it('interdit à quiconque de modifier son propre statut', function () {
    $admin = User::factory()->create();
    $admin->assignRole('secretaire_general');
    $saFiche = Membre::factory()->for($admin)->valide()->create();

    expect($admin->can('changerStatut', [$saFiche, StatutMembre::Suspendu]))->toBeFalse();
});
