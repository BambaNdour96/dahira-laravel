<?php

use App\Models\Membre;
use App\Models\User;
use App\Notifications\RappelCotisation;
use App\Services\CotisationService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');
});

function membreEnRetard(int $moisEcoules = 3, array $attributs = []): Membre
{
    $user = User::factory()->create();
    $user->assignRole('membre');

    return Membre::factory()->for($user)->valide()->create([
        'valide_le' => now()->startOfMonth()->subMonths($moisEcoules),
        ...$attributs,
    ]);
}

it('calcule les retards de toute une liste comme un par un', function () {
    $a = membreEnRetard(3);
    $b = membreEnRetard(0);

    expect(app(CotisationService::class)->retards([$a, $b]))->toBe([$a->id => 2, $b->id => 0]);
});

it('ouvre l\'écran des relances au Jewrine finance', function () {
    membreEnRetard();

    $this->actingAs($this->tresorier)->get('/admin/relances')->assertOk();
});

it('note la relance puis ouvre WhatsApp avec le message prêt', function () {
    $membre = membreEnRetard(3, ['telephone' => '+221771112233', 'prenom' => 'Awa']);

    $reponse = $this->actingAs($this->tresorier)->get(route('relances.whatsapp', $membre));

    $reponse->assertRedirect();
    expect($reponse->headers->get('Location'))->toStartWith('https://wa.me/221771112233?text=')
        ->and(urldecode((string) $reponse->headers->get('Location')))->toContain('Assalamou aleykoum Awa')
        ->and($membre->fresh()->getAttribute('relance_le'))->not->toBeNull();
});

it('interdit à un Jewrine de relancer un membre d\'une autre cellule', function () {
    $jewrine = User::factory()->create();
    $jewrine->assignRole('jewrine_cellule');
    celluleDirigeePar($jewrine);

    $autre = membreEnRetard();

    $this->actingAs($jewrine)->get(route('relances.whatsapp', $autre))->assertForbidden();
});

it('affiche le bandeau de retard dans le compte du membre', function () {
    $membre = membreEnRetard(3);

    $this->actingAs($membre->user)->get('/mon-espace')->assertSee('Cotisation en retard de 2 mois');
});

it('envoie le rappel email aux seuls membres en retard qui ont un email', function () {
    Notification::fake();

    membreEnRetard(3, ['email' => 'retard@exemple.sn']);
    membreEnRetard(0, ['email' => 'ajour@exemple.sn']);
    membreEnRetard(3, ['email' => null]);

    $this->artisan('cotisations:rappeler')->assertSuccessful();

    Notification::assertSentOnDemandTimes(RappelCotisation::class, 1);
});
