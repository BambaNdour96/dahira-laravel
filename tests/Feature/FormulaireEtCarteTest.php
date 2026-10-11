<?php

use App\Enums\NiveauEtudes;
use App\Enums\SituationProfessionnelle;
use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\User;
use App\Services\CarteService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->compte = User::factory()->create();
    $this->compte->assignRole('membre');
});

it('propose les nouveaux niveaux d\'études et la situation d\'entrepreneur', function () {
    expect(NiveauEtudes::Bac->getLabel())->toBe('BAC')
        ->and(NiveauEtudes::Licence->getLabel())->toBe('Licence')
        ->and(NiveauEtudes::Master->getLabel())->toBe('Master')
        ->and(NiveauEtudes::BacPlus5->getLabel())->toBe('BAC + 5 et plus')
        ->and(NiveauEtudes::Autre->getLabel())->toBe('Autre (à préciser)')
        ->and(SituationProfessionnelle::Entrepreneur->getLabel())->toBe('Entrepreneur / Chef d\'entreprise');
});

it('garde la précision du niveau « Autre », et l\'efface pour un autre niveau', function () {
    $membre = Membre::factory()->for($this->compte)->valide()->create();

    $membre->update(['niveau_etudes' => NiveauEtudes::Autre, 'niveau_etudes_autre' => 'Formation professionnelle']);
    expect($membre->fresh()->niveau_etudes_autre)->toBe('Formation professionnelle');

    $membre->update(['niveau_etudes' => NiveauEtudes::Master]);
    expect($membre->fresh()->niveau_etudes_autre)->toBeNull();
});

it('réunit le jour, le mois et l\'année en une date de naissance', function () {
    $membre = Membre::factory()->for($this->compte)->create([
        'statut' => StatutMembre::ACorriger, 'motif_statut' => 'Date à vérifier',
    ]);

    $this->actingAs($this->compte)->get(route('espace.profil'))
        ->assertOk()
        ->assertSee('name="date_naissance_jour"', false)
        ->assertSee('name="date_naissance_annee"', false);

    $this->actingAs($this->compte)->put(route('espace.profil.update'), [
        'date_naissance_jour' => '31', 'date_naissance_mois' => '2', 'date_naissance_annee' => '1990',
    ])->assertSessionHasErrors('date_naissance');

    expect($membre->fresh()->date_naissance?->format('Y-m-d'))->not->toBe('1990-02-31');
});

it('laisse le membre télécharger sa carte en PDF, et à lui seul', function () {
    Membre::factory()->for($this->compte)->valide()->create(['matricule' => 'NDK-2026-0042']);
    app(CarteService::class)->emettre($this->compte->membre()->first());

    $reponse = $this->actingAs($this->compte)->get(route('carte.pdf'));

    $reponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($reponse->headers->get('Content-Disposition'))->toContain('carte-NDK-2026-0042.pdf');

    $this->actingAs($this->compte)->get(route('carte'))
        ->assertSee(route('carte.pdf'), false)
        ->assertSee('Télécharger en image');

    $autre = User::factory()->create();
    $autre->assignRole('membre');
    Membre::factory()->for($autre)->create();
    $this->actingAs($autre)->get(route('carte.pdf'))->assertNotFound();
});
