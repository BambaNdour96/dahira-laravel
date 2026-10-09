<?php

use App\Models\Membre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use App\Services\PaiementService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TarifsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('donne le matricule dans le message de bienvenue, et des liens sans adresse de site', function () {
    $this->seed([RolesSeeder::class, TarifsSeeder::class]);

    $tresorier = User::factory()->create();
    $tresorier->assignRole('tresorier');
    $compte = User::factory()->create();
    $compte->assignRole('membre');
    $membre = Membre::factory()->for($compte)->accepte()->create();

    app(PaiementService::class)->enregistrerEspeces($membre, $tresorier);

    $avis = DB::table('notifications')->where('notifiable_id', $compte->id)->get()
        ->map(fn ($ligne): array => json_decode($ligne->data, true));
    $bienvenue = $avis->firstWhere('titre', 'Bienvenue dans la Daara !');

    expect($bienvenue['message'])->toContain($membre->fresh()->matricule)
        ->and($bienvenue['lien'])->toBe('/ma-carte')
        ->and($avis->pluck('lien')->filter()->every(fn (string $lien): bool => str_starts_with($lien, '/')))->toBeTrue();
});

it('ramène un ancien lien complet à son chemin', function () {
    expect(NotificationMembre::chemin('http://127.0.0.1:8000/recus/12'))->toBe('/recus/12')
        ->and(NotificationMembre::chemin('https://ndk.sn/mes-cotisations?annee=2026'))->toBe('/mes-cotisations?annee=2026')
        ->and(NotificationMembre::chemin(null))->toBeNull();
});
