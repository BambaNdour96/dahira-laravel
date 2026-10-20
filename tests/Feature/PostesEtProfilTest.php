<?php

use App\Enums\NiveauLecture;
use App\Models\Membre;
use App\Models\Poste;
use App\Models\ProfilArabeCoran;
use App\Models\User;
use Database\Seeders\PostesSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, PostesSeeder::class]);
});

it('crée la liste officielle des postes, dans l\'ordre, chacun avec sa casquette', function () {
    expect(Poste::query()->orderBy('ordre')->pluck('nom')->first())->toBe('Jewrine national')
        ->and(Poste::query()->where('actif', true)->count())->toBe(6)
        ->and(Poste::query()->whereNull('code')->exists())->toBeFalse()
        ->and(Poste::query()->where('code', 'suivi_evaluation')->value('actif'))->toBeFalse();
});

it('réserve l\'écran des postes au super administrateur', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_administrateur');
    $jewrine = User::factory()->create();
    $jewrine->assignRole('jewrine_cellule');

    $this->actingAs($admin)->get('/admin/postes')->assertOk();
    $this->actingAs($jewrine)->get('/admin/postes')->assertForbidden();
});

it('calcule le pourcentage de profil complété', function () {
    // La rubrique « Arabe et Coran » vit dans sa propre table : elle compte, mais n'est pas une colonne du membre.
    $vide = Membre::factory()->create(array_fill_keys(array_diff(Membre::CHAMPS_PROFIL, ['arabe_coran']), null));
    $complet = Membre::factory()->create([
        'email' => 'awa@exemple.sn',
        'diplome' => 'Licence',
        'domaine_etudes' => 'Gestion',
        'secteur_activite' => 'Commerce',
    ]);

    ProfilArabeCoran::query()->create(['membre_id' => $complet->id, 'lecture' => NiveauLecture::Couramment]);

    expect($vide->completude())->toBe(0)
        ->and($complet->completude())->toBe(100);
});
