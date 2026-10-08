<?php

use App\Models\Cellule;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Presence;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CarteService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function membreAvecCarte(Cellule $cellule): array
{
    $compte = User::factory()->create();
    $compte->assignRole('membre');
    $membre = Membre::factory()->for($compte)->for($cellule)->valide()->create();
    $carte = app(CarteService::class)->emettre($membre);

    return [$compte, $membre, $carte];
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->super = User::factory()->create();
    $this->super->assignRole(['super_administrateur', 'administrateur']);

    $this->kaolack = Cellule::factory()->create(['nom' => 'Kaolack']);
    $this->dakar = Cellule::factory()->create(['nom' => 'Dakar']);

    [$this->controleur] = membreAvecCarte($this->kaolack);
    [$this->compteMembre, $this->membre, $this->carte] = membreAvecCarte($this->kaolack);

    $this->actingAs($this->super);
    $this->evenement = Evenement::query()->create([
        'titre' => 'Magal 2026', 'lieu' => 'Kaolack', 'debut' => now()->addHour(),
    ]);
    $this->evenement->controleurs()->attach($this->controleur->id);
});

it('prévient les membres concernés d\'un nouvel événement de cellule', function () {
    [$deKaolack] = membreAvecCarte($this->kaolack);
    [$deDakar] = membreAvecCarte($this->dakar);

    Evenement::query()->create(['titre' => 'Réunion', 'lieu' => 'Kaolack', 'debut' => now()->addDays(3), 'cellule_id' => $this->kaolack->id]);

    $recues = fn (User $compte): int => DB::table('notifications')
        ->where('notifiable_id', $compte->id)->where('type', NotificationMembre::class)->where('data', 'like', '%union%')->count();

    expect($recues($deKaolack))->toBe(1)->and($recues($deDakar))->toBe(0);
});

it('montre le bouton de présence au contrôleur, et à lui seul', function () {
    $this->actingAs($this->controleur)->get(route('verification', $this->carte->jeton))
        ->assertSee('Marquer présent : Magal 2026');

    $this->actingAs($this->compteMembre)->get(route('verification', $this->carte->jeton))
        ->assertDontSee('Marquer présent');
});

it('enregistre la présence une seule fois', function () {
    $this->actingAs($this->controleur)->post(route('presences.store', [$this->carte->jeton, $this->evenement]))
        ->assertRedirect(route('verification', $this->carte->jeton));
    $this->actingAs($this->controleur)->post(route('presences.store', [$this->carte->jeton, $this->evenement]));

    expect(Presence::query()->count())->toBe(1);

    $this->actingAs($this->compteMembre)->get('/evenements')->assertOk()->assertSee('Présent ✓');
});

it('refuse la présence à un non-contrôleur', function () {
    $this->actingAs($this->compteMembre)->post(route('presences.store', [$this->carte->jeton, $this->evenement]))
        ->assertSessionHas('presence_erreur');

    expect(Presence::query()->count())->toBe(0);
});

it('refuse de scanner en dehors du jour de l\'événement', function () {
    $this->travel(3)->days();

    $this->actingAs($this->controleur)->post(route('presences.store', [$this->carte->jeton, $this->evenement]))
        ->assertSessionHas('presence_erreur');

    expect(Presence::query()->count())->toBe(0);
});

it('refuse une carte révoquée', function () {
    app(CarteService::class)->revoquer($this->carte, null, 'Perdue');

    $this->actingAs($this->controleur)->post(route('presences.store', [$this->carte->jeton, $this->evenement]))
        ->assertNotFound();
});

it('réserve les événements et l\'export des présences au super administrateur', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrateur');

    $this->actingAs($this->super)->get('/admin/evenements')->assertOk();
    $this->actingAs($admin)->get('/admin/evenements')->assertForbidden();

    $this->actingAs($this->super)->get(route('evenements.presences.export', $this->evenement))->assertOk();
    $this->actingAs($admin)->get(route('evenements.presences.export', $this->evenement))->assertForbidden();
});

it('annonce les prochains événements dans le compte du membre', function () {
    $this->actingAs($this->compteMembre)->get('/mon-espace')->assertSee('Prochains événements')->assertSee('Magal 2026');
});
