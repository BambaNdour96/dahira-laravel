<?php

use App\Models\Connexion;
use App\Models\JournalActivite;
use App\Models\User;
use App\Services\ConnexionEnTantQue;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->super = User::factory()->create();
    $this->super->assignRole(['super_administrateur', 'administrateur']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(['secretaire_general', 'administrateur']);

    $this->membre = User::factory()->create();
    $this->membre->assignRole('membre');
});

function lienEnTantQue(User $cible): string
{
    return URL::temporarySignedRoute('en-tant-que', now()->addMinutes(5), ['user' => $cible->id]);
}

it('réserve le journal et l\'historique des connexions au super administrateur', function (string $adresse) {
    $this->actingAs($this->super)->get($adresse)->assertOk();
    $this->actingAs($this->admin)->get($adresse)->assertForbidden();
})->with(['/admin/journal-activites', '/admin/connexions']);

it('enregistre les connexions réussies et échouées', function () {
    $user = User::factory()->create(['telephone' => '+221765556677', 'password' => 'motdepasse']);
    $user->assignRole('membre');

    $this->post('/connexion', ['telephone' => '765556677', 'password' => 'mauvais']);
    $this->post('/connexion', ['telephone' => '765556677', 'password' => 'motdepasse']);

    expect(Connexion::query()->where('reussie', false)->where('telephone', '+221765556677')->exists())->toBeTrue()
        ->and(Connexion::query()->where('reussie', true)->where('user_id', $user->id)->exists())->toBeTrue();
});

it('permet au super administrateur de voir la plateforme comme un membre, puis de revenir', function () {
    $this->actingAs($this->super)->get(lienEnTantQue($this->membre))->assertRedirect(route('espace'));
    $this->assertAuthenticatedAs($this->membre);

    $this->withSession([ConnexionEnTantQue::CLE => $this->super->id])
        ->post('/retour-mon-compte')
        ->assertRedirect('/admin/comptes-bureau');
    $this->assertAuthenticatedAs($this->super);

    expect(JournalActivite::query()->where('evenement', 'en_tant_que')->exists())->toBeTrue();
});

it('refuse « voir en tant que » à un simple administrateur', function () {
    $this->actingAs($this->admin)->get(lienEnTantQue($this->membre))->assertForbidden();
});

it('refuse de prendre la place d\'un super administrateur', function () {
    $autreSuper = User::factory()->create();
    $autreSuper->assignRole('super_administrateur');

    $this->actingAs($this->super)->get(lienEnTantQue($autreSuper))->assertForbidden();
});

it('refuse un lien « voir en tant que » non signé', function () {
    $this->actingAs($this->super)->get('/en-tant-que/'.$this->membre->id)->assertForbidden();
});
