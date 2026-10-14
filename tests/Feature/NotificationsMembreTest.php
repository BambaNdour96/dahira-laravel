<?php

use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Connexion;
use App\Models\Membre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\CarteService;
use App\Services\CotisationService;
use App\Services\PaiementService;
use App\Services\StatutMembreService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function avisDe(User $utilisateur): array
{
    return DB::table('notifications')
        ->where('notifiable_id', $utilisateur->id)
        ->where('type', NotificationMembre::class)
        ->orderBy('created_at')
        ->pluck('data')
        ->map(fn (string $data): string => json_decode($data, true)['titre'])
        ->all();
}

beforeEach(function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);
    config(['dahira.cotisations_debut' => '2000-01']);

    $this->tresorier = User::factory()->create();
    $this->tresorier->assignRole('jewrine_finance');

    $this->compte = User::factory()->create(['telephone' => '+221776665544', 'password' => 'motdepasse1']);
    $this->compte->assignRole('membre');
});

it('prévient le membre que sa demande est acceptée', function () {
    $membre = Membre::factory()->for($this->compte)->for(Cellule::factory())->create(['statut' => StatutMembre::EnAttente]);

    app(StatutMembreService::class)->changer($membre, StatutMembre::Accepte, null);

    expect(avisDe($this->compte))->toBe(['Votre demande est acceptée']);
});

it('souhaite la bienvenue et confirme le paiement des frais de carte', function () {
    $membre = Membre::factory()->for($this->compte)->accepte()->create();

    app(PaiementService::class)->enregistrerEspeces($membre, $this->tresorier);

    expect(avisDe($this->compte))->toContain('Paiement confirmé')
        ->and(avisDe($this->compte))->toContain('Bienvenue dans la Daara !');
});

it('prévient le membre d\'un paiement refusé', function () {
    $membre = Membre::factory()->for($this->compte)->valide()->create(['valide_le' => now()->subMonth()]);
    $paiement = app(CotisationService::class)->declarer($membre, 'T_AVISREFUS000001', 1);

    app(PaiementService::class)->refuser($paiement, $this->tresorier, 'Référence introuvable');

    expect(avisDe($this->compte))->toBe(['Paiement refusé']);
});

it('prévient le membre que sa carte a été remplacée', function () {
    $membre = Membre::factory()->for($this->compte)->valide()->create();
    $carte = app(CarteService::class)->emettre($membre);

    app(CarteService::class)->revoquer($carte, null, 'Carte perdue');

    expect(avisDe($this->compte))->toContain('Votre carte a été remplacée');
});

it('signale une connexion depuis un nouvel appareil, mais pas la première', function () {
    $this->post('/connexion', ['telephone' => '77 666 55 44', 'password' => 'motdepasse1']);
    $this->post('/deconnexion');
    expect(avisDe($this->compte))->toBe([]);

    Connexion::query()->where('user_id', $this->compte->id)->update(['created_at' => now()->subDay()]);

    $this->withHeader('User-Agent', 'Un tout nouveau navigateur')
        ->post('/connexion', ['telephone' => '77 666 55 44', 'password' => 'motdepasse1']);

    expect(avisDe($this->compte))->toBe(['Nouvelle connexion à votre compte']);
});

it('affiche les notifications du membre, puis les marque comme lues', function () {
    Membre::factory()->for($this->compte)->valide()->create();
    $this->compte->notify(new NotificationMembre('Message de test', 'Contenu du message.'));

    $this->actingAs($this->compte)->get('/mon-espace')->assertSee('Notifications (1 nouvelle(s))');

    $this->actingAs($this->compte)->get('/mes-notifications')->assertOk()->assertSee('Message de test');

    expect($this->compte->unreadNotifications()->count())->toBe(0);
});

it('montre les notifications dans le tiroir, sur toutes les pages, et les marque lues à l\'ouverture', function () {
    Membre::factory()->for($this->compte)->valide()->create();
    $this->compte->notify(new NotificationMembre('Avis du tiroir', 'Visible partout.'));

    $this->actingAs($this->compte)->get('/mon-espace')->assertSee('tiroir-notifications', false)
        ->assertSee('Avis du tiroir');

    $this->actingAs($this->compte)->post(route('notifications.lues'))->assertNoContent();

    expect($this->compte->unreadNotifications()->count())->toBe(0);
});

it('distingue les notifications nouvelles des notifications lues', function () {
    Membre::factory()->for($this->compte)->valide()->create();
    $this->compte->notify(new NotificationMembre('Avis déjà lu', 'Ancien.'));
    $this->compte->unreadNotifications()->update(['read_at' => now()]);
    $this->compte->notify(new NotificationMembre('Avis tout neuf', 'Récent.'));

    $this->actingAs($this->compte)->get('/mon-espace')
        ->assertSee('Nouveau')
        ->assertSee('opacity-75', false)
        ->assertSee('tiroir-menu', false)
        ->assertSee('Paramètres')
        ->assertSee('Se déconnecter');
});
