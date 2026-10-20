<?php

use App\Enums\StatutMembre;
use App\Enums\StatutPaiement;
use App\Models\CampagneRenouvellement;
use App\Models\Collecte;
use App\Models\Instance;
use App\Models\Membre;
use App\Models\Paiement;
use App\Models\ProfilArabeCoran;
use App\Models\Reunion;
use App\Models\User;
use App\Services\CotisationService;
use Database\Seeders\RecetteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('charge en une commande un jeu de recette complet, avec un compte par casquette', function () {
    config(['dahira.cotisations_debut' => '2000-01']);
    $this->artisan('recette:charger')->assertSuccessful();

    $roles = fn (string $telephone): array => User::query()->where('telephone', $telephone)->firstOrFail()->getRoleNames()->all();

    expect($roles(RecetteSeeder::TELEPHONE_SUPER))->toContain('super_administrateur')
        ->and($roles('+221770100002'))->toContain('jewrine_national', 'comite_directeur')
        ->and($roles('+221770100003'))->toContain('secretaire_general')
        ->and($roles('+221770100004'))->toContain('jewrine_finance')
        ->and($roles('+221770100013'))->toContain('jewrine_cellule')
        ->and($roles('+221770100026'))->toContain('representant_finance')
        ->and($roles('+221770100041'))->toContain('jewrine_commission')
        ->and($roles('+221770100045'))->toContain('suivi_evaluation')
        ->and(Auth::attempt(['telephone' => '+221770100101', 'password' => RecetteSeeder::MOT_DE_PASSE]))->toBeTrue();

    $cotisations = app(CotisationService::class);
    $aJour = Membre::query()->where('telephone', '+221770100101')->firstOrFail();
    $enRetard = Membre::query()->where('telephone', '+221770100105')->firstOrFail();
    $fils = Membre::query()->where('prenom', 'Fallou')->whereNotNull('responsable_id')->firstOrFail();

    expect($cotisations->moisEnRetard($aJour))->toBe([])
        ->and(count($cotisations->moisEnRetard($enRetard)))->toBeGreaterThan(0)
        ->and($fils->estEnfant())->toBeTrue()
        ->and(Paiement::query()->where('statut', StatutPaiement::Declare->value)->count())->toBeGreaterThanOrEqual(4)
        ->and(Membre::query()->where('statut', StatutMembre::EnAttente->value)->exists())->toBeTrue()
        ->and(Collecte::query()->firstOrFail()->engagements()->count())->toBe(7)
        ->and(Instance::query()->where('type', Instance::CDGMT)->firstOrFail()->commissions()->count())->toBe(3)
        ->and(Reunion::query()->firstOrFail()->estPubliee())->toBeTrue()
        ->and(CampagneRenouvellement::ouverte())->not->toBeNull()
        ->and(ProfilArabeCoran::query()->count())->toBe(7);

    $this->artisan('recette:charger')->assertFailed();
});

it('refuse de charger les données de recette en production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('recette:charger')->assertFailed();
    expect(User::query()->where('telephone', RecetteSeeder::TELEPHONE_SUPER)->exists())->toBeFalse();
});

it('prévient quand le démarrage des cotisations empêche de voir des retards', function () {
    config(['dahira.cotisations_debut' => now()->addMonth()->format('Y-m')]);

    $this->artisan('recette:charger')->expectsOutputToContain('COTISATIONS_DEBUT')->assertSuccessful();
});
