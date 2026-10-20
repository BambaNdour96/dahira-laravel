<?php

use App\Enums\NiveauEtudes;
use App\Enums\NiveauLecture;
use App\Enums\SituationProfessionnelle;
use App\Models\Membre;
use App\Models\ProfilArabeCoran;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('vide le diplôme et le domaine quand le niveau d\'études est « Aucun »', function () {
    $membre = Membre::factory()->create(['diplome' => 'Licence', 'domaine_etudes' => 'Droit']);

    $membre->update(['niveau_etudes' => NiveauEtudes::Aucun]);

    expect($membre->fresh()->diplome)->toBeNull()->and($membre->fresh()->domaine_etudes)->toBeNull();
});

it('vide la profession et le secteur d\'un retraité, mais garde la profession d\'un sans-emploi', function () {
    $retraite = Membre::factory()->create(['profession' => 'Enseignant', 'secteur_activite' => 'Éducation']);
    $retraite->update(['situation_professionnelle' => SituationProfessionnelle::Retraite]);

    $sansEmploi = Membre::factory()->create(['profession' => 'Électricien', 'situation_professionnelle' => SituationProfessionnelle::Salarie]);
    $sansEmploi->update(['situation_professionnelle' => SituationProfessionnelle::SansEmploi]);

    expect($retraite->fresh()->profession)->toBeNull()
        ->and($retraite->fresh()->secteur_activite)->toBeNull()
        ->and($sansEmploi->fresh()->profession)->toBe('Électricien');
});

it('permet à un retraité sans études d\'atteindre 100 % de profil', function () {
    $membre = Membre::factory()->create([
        'email' => 'retraite@exemple.sn', 'adresse' => 'Médina', 'ville' => 'Kaolack',
        'niveau_etudes' => NiveauEtudes::Aucun,
        'situation_professionnelle' => SituationProfessionnelle::Retraite,
        'langues' => ['wolof'],
    ]);
    ProfilArabeCoran::query()->create(['membre_id' => $membre->id, 'lecture' => NiveauLecture::Difficulte]);

    expect($membre->fresh()->completude())->toBe(100);
});
