<?php

use App\Http\Middleware\ExpirationInactivite;
use App\Listeners\UneSeuleSessionParCompte;
use App\Models\Membre;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

it('déconnecte le bureau après 10 minutes sans activité', function () {
    $tresorier = User::factory()->create();
    $tresorier->assignRole('tresorier');

    $this->actingAs($tresorier)->get('/admin')->assertOk();

    $this->travel(11)->minutes();

    $this->get('/admin')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('laisse 25 minutes au super administrateur', function () {
    $super = User::factory()->create();
    $super->assignRole(['super_administrateur', 'administrateur']);

    $this->actingAs($super)->get('/admin')->assertOk();

    $this->travel(11)->minutes();
    $this->get('/admin')->assertOk();

    $this->travel(26)->minutes();
    $this->get('/admin')->assertRedirect(route('login'));
});

it('déconnecte un membre après 2 minutes sans activité', function () {
    $compte = User::factory()->create();
    $compte->assignRole('membre');
    Membre::factory()->for($compte)->valide()->create();

    $this->actingAs($compte)->get('/mon-espace')->assertOk();

    $this->travel(1)->minutes();
    $this->get('/mon-espace')->assertOk();

    $this->travel(3)->minutes();
    $this->get('/mon-espace')->assertRedirect(route('login'));
});

it('ferme les autres sessions du compte à une nouvelle connexion', function () {
    config(['session.driver' => 'database']);

    $compte = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'ancienne-session-1', 'user_id' => $compte->id, 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ['id' => 'ancienne-session-2', 'user_id' => $compte->id, 'payload' => '', 'last_activity' => now()->getTimestamp()],
    ]);

    (new UneSeuleSessionParCompte)->handle(new Login('web', $compte, false));

    expect(DB::table('sessions')->where('user_id', $compte->id)->count())->toBe(0);
});

it('montre les personnes connectées au seul super administrateur', function () {
    $super = User::factory()->create(['name' => 'Super Admin']);
    $super->assignRole(['super_administrateur', 'administrateur']);

    $membre = User::factory()->create(['name' => 'Awa Connectée']);
    DB::table('sessions')->insert([
        'id' => 'session-de-awa', 'user_id' => $membre->id, 'ip_address' => '41.82.0.1',
        'user_agent' => 'Mozilla/5.0 (Linux; Android 13) Chrome/120 Mobile', 'payload' => '', 'last_activity' => now()->getTimestamp(),
    ]);

    $this->actingAs($super)->get('/admin/connectes')->assertOk()->assertSee('Awa Connectée')->assertSee('Téléphone · Chrome');

    $tresorier = User::factory()->create();
    $tresorier->assignRole('tresorier');
    $this->actingAs($tresorier)->get('/admin/connectes')->assertForbidden();
});

it('ne compte pas la mise à jour automatique des notifications comme une activité', function () {
    $rafraichissement = Request::create('/livewire-abc/update', 'POST', [
        'components' => [['updates' => [], 'calls' => [['method' => '$refresh', 'params' => []]]]],
    ]);
    $clic = Request::create('/livewire-abc/update', 'POST', [
        'components' => [['updates' => [], 'calls' => [['method' => 'mountAction', 'params' => ['confirmer']]]]],
    ]);

    expect(ExpirationInactivite::enArrierePlan($rafraichissement))->toBeTrue()
        ->and(ExpirationInactivite::enArrierePlan($clic))->toBeFalse();
});
