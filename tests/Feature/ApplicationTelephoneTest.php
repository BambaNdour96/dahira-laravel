<?php

use App\Models\Membre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\WebPush\WebPushChannel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->compte = User::factory()->create();
    $this->compte->assignRole('membre');
    Membre::factory()->for($this->compte)->valide()->create();
});

it('affiche la barre d\'onglets aux membres connectés', function () {
    $this->actingAs($this->compte)->get('/mon-espace')
        ->assertSee('barre-onglets', false)
        ->assertSee('Engagements');

    auth()->logout();
    $this->get('/')->assertDontSee('id="barre-onglets"', false);
});

it('enregistre puis retire l\'abonnement aux notifications du téléphone', function () {
    $abonnement = [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
        'keys' => ['p256dh' => 'cle-publique-du-telephone', 'auth' => 'secret-du-telephone'],
    ];

    $this->actingAs($this->compte)->postJson(route('notifications-telephone.store'), $abonnement)->assertNoContent();
    expect($this->compte->pushSubscriptions()->count())->toBe(1);

    $this->actingAs($this->compte)->deleteJson(route('notifications-telephone.destroy'), ['endpoint' => $abonnement['endpoint']])->assertNoContent();
    expect($this->compte->pushSubscriptions()->count())->toBe(0);
});

it('envoie les avis sur le téléphone seulement si le membre l\'a activé', function () {
    $avis = new NotificationMembre('Paiement confirmé', 'Votre reçu est disponible.', '/mes-recus');

    expect($avis->via($this->compte))->not->toContain(WebPushChannel::class);

    $this->compte->updatePushSubscription('https://fcm.googleapis.com/fcm/send/xyz', 'cle', 'secret', 'aes128gcm');

    expect($avis->via($this->compte->fresh()))->toContain(WebPushChannel::class);

    $message = $avis->toWebPush($this->compte, $avis)->toArray();
    expect($message['title'])->toBe('Paiement confirmé')
        ->and($message['body'])->toBe('Votre reçu est disponible.')
        ->and($message['data']['url'])->toBe('/mes-recus');
});

it('propose les notifications sur le téléphone dans les Paramètres', function () {
    $this->actingAs($this->compte)->get(route('parametres'))
        ->assertSee('Notifications sur ce téléphone')
        ->assertSee('data-notifications-telephone', false);
});
