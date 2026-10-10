<?php

use App\Http\Middleware\ExpirationInactivite;
use App\Listeners\UneSeuleSessionParCompte;
use App\Models\Membre;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

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

describe('les sessions des membres', function () {
    beforeEach(function () {
        $this->membre = User::factory()->create(['telephone' => '+221770001234', 'password' => 'motdepasse1']);
        $this->membre->assignRole('membre');
        Membre::factory()->for($this->membre)->valide()->create();
        RateLimiter::for('connexion', fn () => Limit::none());
    });

    it('ramène le membre sur la même page après 2 minutes sans activité', function () {
        $this->actingAs($this->membre)->get('/mes-recus')->assertOk();

        $this->travel(3)->minutes();
        $this->get('/mes-recus')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/connexion', ['telephone' => '77 000 12 34', 'password' => 'motdepasse1'])
            ->assertRedirect(url('/mes-recus'));
    });

    it('ferme la session au bout de 10 minutes, même actif, et ramène à l\'accueil', function () {
        $this->actingAs($this->membre)->get('/mes-recus')->assertOk();

        foreach (range(1, 9) as $minute) {
            $this->travel(1)->minutes();
            $this->get('/mes-recus')->assertOk();
        }

        $this->travel(1)->minutes();
        $this->get('/mes-recus')->assertRedirect(route('login'));

        $this->post('/connexion', ['telephone' => '77 000 12 34', 'password' => 'motdepasse1'])
            ->assertRedirect(route('espace'));
    });

    it('compte un toucher de l\'écran comme une activité', function () {
        $this->actingAs($this->membre)->get('/mes-recus')->assertOk();

        $this->travel(90)->seconds();
        $this->post(route('session.activite'))->assertNoContent();

        $this->travel(90)->seconds();
        $this->get('/mes-recus')->assertOk();
    });

    it('ne limite pas la durée de session du bureau', function () {
        $tresorier = User::factory()->create();
        $tresorier->assignRole('tresorier');

        $this->actingAs($tresorier)->get('/admin')->assertOk();

        foreach (range(1, 11) as $minute) {
            $this->travel(1)->minutes();
            $this->get('/admin')->assertOk();
        }
    });
});

it('ne ferme jamais les sessions des autres comptes à une connexion', function () {
    config(['session.driver' => 'database']);

    $awa = User::factory()->create(['telephone' => '+221770000101', 'password' => 'motdepasse1']);
    $awa->assignRole('membre');
    Membre::factory()->for($awa)->valide()->create();
    $moussa = User::factory()->create();

    DB::table('sessions')->insert([
        ['id' => 'session-awa-ailleurs', 'user_id' => $awa->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ['id' => 'session-de-moussa', 'user_id' => $moussa->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => now()->getTimestamp()],
    ]);

    $this->post('/connexion', ['telephone' => '77 000 01 01', 'password' => 'motdepasse1']);

    expect(DB::table('sessions')->where('id', 'session-de-moussa')->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'session-awa-ailleurs')->exists())->toBeFalse();
});
