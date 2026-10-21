<?php

use App\Enums\ModeCollecte;
use App\Enums\StatutCollecte;
use App\Models\Collecte;
use App\Models\Evenement;
use App\Models\Membre;
use App\Models\Rappel;
use App\Models\User;
use App\Notifications\RappelProgramme;
use App\Services\CollecteService;
use App\Services\RappelService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function programmeDeRappel(array $attributs = []): Collecte
{
    return Collecte::query()->create(array_merge([
        'titre' => 'Magal de Touba 2026',
        'type_programme_id' => typeProgramme('magal'),
        'mode' => ModeCollecte::Jayante,
        'ouverture' => now()->subDays(2),
        'statut' => StatutCollecte::Ouverte,
    ], $attributs));
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->compte = User::factory()->create();
    $this->compte->assignRole('membre');
    $this->membre = Membre::factory()->for($this->compte)->valide()->create();
    $this->rappels = app(RappelService::class);
});

it('rappelle au membre de fixer sa participation, une seule fois par jour', function () {
    $collecte = programmeDeRappel();

    $rappel = $this->rappels->fenetre($this->membre);

    expect($rappel['message'])->toContain('pas encore fixé')
        ->and($rappel['bouton'])->toBe('Fixer ma participation')
        ->and($rappel['url'])->toEndWith('#collecte-'.$collecte->id)
        ->and($this->rappels->fenetre($this->membre))->toBeNull();
});

it('rappelle le reste à verser et la date limite, puis se tait quand tout est réglé', function () {
    $collecte = programmeDeRappel(['date_limite' => now()->addDays(5)]);
    app(CollecteService::class)->fixerParMembre($collecte, $this->membre, 20000);

    $rappel = $this->rappels->fenetre($this->membre);
    expect($rappel['message'])->toContain('20 000 FCFA')->toContain('dans 5 jour(s)')
        ->and($rappel['bouton'])->toBe('Verser maintenant');

    $this->travel(1)->days();
    $tresorier = User::factory()->create();
    $tresorier->assignRole('jewrine_finance');
    app(CollecteService::class)->enregistrerEspeces($collecte, $this->membre, 20000, $tresorier);

    expect($this->rappels->fenetre($this->membre))->toBeNull();
});

it('donne la priorité à la veille du jour J', function () {
    $evenement = Evenement::query()->create(['titre' => 'Magal', 'lieu' => 'Touba', 'debut' => now()->addDay()->setTime(10, 0)]);
    programmeDeRappel(['evenement_id' => $evenement->id]);
    programmeDeRappel(['titre' => 'Prokhane 2026', 'date_limite' => now()->subDay()]);

    $rappel = $this->rappels->fenetre($this->membre);

    expect($rappel['message'])->toStartWith('Demain')->toContain('Touba')
        ->and($rappel['bouton'])->toBe('Voir ma carte');
});

it('ne rappelle rien quand le bureau a désactivé les rappels', function () {
    programmeDeRappel(['rappels_actifs' => false]);

    expect($this->rappels->fenetre($this->membre))->toBeNull();
});

it('affiche la fenêtre avec ses deux boutons, sauf sur la page des engagements', function () {
    programmeDeRappel();

    $this->actingAs($this->compte)->get(route('engagements.index'))->assertDontSee('rappel-programme', false);

    $this->actingAs($this->compte)->get('/mon-espace')
        ->assertSee('rappel-programme', false)
        ->assertSee('Plus tard')
        ->assertSee('Fixer ma participation');
});

it('envoie les notifications au bon moment, jamais de nuit ni en double', function () {
    Notification::fake();
    $this->travelTo(now()->setTime(10, 0));
    programmeDeRappel(['ouverture' => now()]);

    expect($this->rappels->envoyer())->toBe(1)
        ->and($this->rappels->envoyer())->toBe(0)
        ->and($this->rappels->envoyer(now()->setTime(22, 0)))->toBe(0)
        ->and(Rappel::query()->where('canal', 'notification')->count())->toBe(1);

    Notification::assertSentTo($this->compte, RappelProgramme::class);
    $this->artisan('programmes:rappeler')->assertSuccessful();
});

it('félicite le membre à jour et annonce les caps franchis par la Daara', function () {
    Notification::fake();
    $collecte = programmeDeRappel(['objectif' => 10000, 'visible_membres' => true]);
    app(CollecteService::class)->fixerParMembre($collecte, $this->membre, 10000);

    $tresorier = User::factory()->create();
    $tresorier->assignRole('jewrine_finance');
    app(CollecteService::class)->enregistrerEspeces($collecte, $this->membre, 10000, $tresorier);

    Notification::assertSentTo($this->compte, RappelProgramme::class, fn (RappelProgramme $avis): bool => str_contains($avis->message, 'Barakallahou'));
    Notification::assertSentTo($this->compte, RappelProgramme::class, fn (RappelProgramme $avis): bool => str_contains($avis->message, 'atteint l\'objectif'));
});
