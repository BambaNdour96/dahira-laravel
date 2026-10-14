<?php

use App\Enums\StatutMembre;
use App\Filament\Widgets\MesTaches;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\User;
use App\Services\CotisationService;
use App\Services\StatutMembreService;
use Database\Seeders\PostesSeeder;
use Database\Seeders\ReglesValidationSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function notificationsDe(User $utilisateur): int
{
    return DB::table('notifications')->where('notifiable_id', $utilisateur->id)->count();
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class, PostesSeeder::class, ReglesValidationSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);

    $this->jewrine = User::factory()->create();
    $this->jewrine->assignRole('jewrine_cellule');
    $this->saCellule = celluleDirigeePar($this->jewrine);

    $this->autreJewrine = User::factory()->create();
    $this->autreJewrine->assignRole('jewrine_cellule');
    celluleDirigeePar($this->autreJewrine);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');

    $this->super = User::factory()->create();
    $this->super->assignRole('super_administrateur');
});

it('prévient le seul Jewrine de la cellule d\'une nouvelle demande', function () {
    Membre::factory()->for($this->saCellule)->create(['statut' => StatutMembre::EnAttente]);

    expect(notificationsDe($this->jewrine))->toBe(1)
        ->and(notificationsDe($this->autreJewrine))->toBe(0);
});

it('prévient le Jewrine quand une fiche corrigée lui revient', function () {
    $membre = Membre::factory()->for($this->saCellule)->create(['statut' => StatutMembre::ACorriger]);

    app(StatutMembreService::class)->changer($membre, StatutMembre::EnAttente, null);

    expect(notificationsDe($this->jewrine))->toBe(1);
});

it('prévient le Jewrine finance d\'un paiement à vérifier', function () {
    $membre = Membre::factory()->valide()->create(['valide_le' => now()->subMonths(2)]);

    app(CotisationService::class)->declarer($membre, 'T_TACHES000000001', 1);

    expect(notificationsDe($this->tresorier))->toBe(1)
        ->and(notificationsDe($this->super))->toBe(0);
});

it('confie le paiement du Jewrine finance à ses validateurs, sans le prévenir lui-même', function () {
    $secretaire = User::factory()->create();
    $secretaire->assignRole('secretaire_general');
    Membre::factory()->for($secretaire)->valide()->create();
    nommerPourLeTest($secretaire, 'secretaire_general');

    Membre::factory()->for($this->tresorier)->valide()->create(['valide_le' => now()->subMonths(2)]);
    nommerPourLeTest($this->tresorier, 'jewrine_finance');

    app(CotisationService::class)->declarer($this->tresorier->fresh()->membre, 'T_TACHES000000002', 1);

    expect(notificationsDe($secretaire))->toBe(1)
        ->and(notificationsDe($this->tresorier))->toBe(0);
});

it('montre au Jewrine ses demandes à examiner', function () {
    Membre::factory()->for($this->saCellule)->count(2)->create(['statut' => StatutMembre::EnAttente]);

    $this->actingAs($this->jewrine);

    Livewire::test(MesTaches::class)->assertSee('Demandes à examiner')->assertSee('2');
});

it('alerte le super administrateur des tâches en attente depuis plus de 3 jours', function () {
    $ancienne = Membre::factory()->for($this->saCellule)->create(['statut' => StatutMembre::EnAttente]);
    DB::table('membres')->where('id', $ancienne->id)->update(['updated_at' => now()->subDays(4)]);

    $this->artisan('taches:surveiller')->assertSuccessful();

    expect(notificationsDe($this->super))->toBe(1);
});

it('ne dérange personne quand rien n\'est en retard', function () {
    Membre::factory()->for($this->saCellule)->create(['statut' => StatutMembre::EnAttente]);

    $this->artisan('taches:surveiller')->assertSuccessful();

    expect(notificationsDe($this->super))->toBe(0)
        ->and(Paiement::query()->count())->toBe(0);
});
