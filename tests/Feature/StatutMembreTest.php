<?php

use App\Enums\StatutMembre;
use App\Models\Membre;
use App\Models\User;
use App\Services\StatutMembreService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('attribue un matricule uniquement à la validation', function () {
    $membre = Membre::factory()->create();
    expect($membre->matricule)->toBeNull();

    app(StatutMembreService::class)->changer($membre, StatutMembre::Valide, User::factory()->create());

    $membre->refresh();
    expect($membre->matricule)->toBe('NDD-'.now()->year.'-0001')
        ->and($membre->statut)->toBe(StatutMembre::Valide)
        ->and($membre->valide_le)->not->toBeNull()
        ->and($membre->date_adhesion)->not->toBeNull();
});

it('incrémente le compteur du matricule', function () {
    $service = app(StatutMembreService::class);
    $premier = Membre::factory()->create();
    $second = Membre::factory()->create();

    $service->changer($premier, StatutMembre::Valide, null);
    $service->changer($second, StatutMembre::Valide, null);

    expect($second->refresh()->matricule)->toEndWith('-0002');
});

it('ne change jamais un matricule déjà attribué', function () {
    $service = app(StatutMembreService::class);
    $membre = Membre::factory()->create();

    $service->changer($membre, StatutMembre::Valide, null);
    $matricule = $membre->refresh()->matricule;

    $service->changer($membre, StatutMembre::Suspendu, null, 'Absences répétées');
    $service->changer($membre, StatutMembre::Valide, null);

    expect($membre->refresh()->matricule)->toBe($matricule);
});

it('refuse une transition interdite', function () {
    $membre = Membre::factory()->create();

    app(StatutMembreService::class)->changer($membre, StatutMembre::Suspendu, null, 'Motif');
})->throws(DomainException::class);

it('exige un motif pour un rejet', function () {
    $membre = Membre::factory()->create();

    app(StatutMembreService::class)->changer($membre, StatutMembre::Rejete, null);
})->throws(DomainException::class);

it('normalise le téléphone à l\'enregistrement', function () {
    $membre = Membre::factory()->create(['telephone' => '77 555 44 33']);

    expect($membre->refresh()->telephone)->toBe('+221775554433');
});
