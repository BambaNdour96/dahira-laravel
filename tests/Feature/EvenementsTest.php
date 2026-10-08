<?php

use App\Models\Cellule;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Presence;
use App\Models\User;
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

it('ne prévient plus les membres à la création d\'un événement, le site s\'en charge', function () {
    Evenement::query()->create(['titre' => 'Conférence', 'lieu' => 'Dakar', 'debut' => now()->addDays(5)]);

    expect(DB::table('notifications')->where('notifiable_id', $this->compteMembre->id)->where('data', 'like', '%Conf%')->count())->toBe(0);
});

it('donne au membre l\'accès à ses participations, sans bloc d\'événements à venir', function () {
    $this->actingAs($this->compteMembre)->get('/mon-espace')
        ->assertSee('Mes participations')
        ->assertDontSee('Prochains événements');

    $this->actingAs($this->compteMembre)->get('/evenements')->assertOk()->assertSee('Aucune participation enregistrée');
});
