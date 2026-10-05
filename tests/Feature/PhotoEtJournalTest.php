<?php

use App\Enums\StatutMembre;
use App\Models\JournalActivite;
use App\Models\Membre;
use App\Models\User;
use App\Services\StatutMembreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('recadre la photo en JPEG 800 × 800 et supprime le fichier d\'origine', function () {
    Storage::fake('local');
    $origine = UploadedFile::fake()->image('photo.png', 1200, 900)->store('photos', 'local');

    $membre = Membre::factory()->create(['photo_path' => $origine]);

    expect($membre->photo_path)->toEndWith('.jpg')
        ->and(Storage::disk('local')->exists($origine))->toBeFalse()
        ->and(Storage::disk('local')->exists($membre->photo_path))->toBeTrue();

    [$largeur, $hauteur] = getimagesize(Storage::disk('local')->path($membre->photo_path));
    expect($largeur)->toBe(800)->and($hauteur)->toBe(800);
});

it('supprime l\'ancienne photo quand on la remplace', function () {
    Storage::fake('local');
    $membre = Membre::factory()->create([
        'photo_path' => UploadedFile::fake()->image('a.png', 900, 900)->store('photos', 'local'),
    ]);
    $ancienne = $membre->photo_path;

    $membre->update(['photo_path' => UploadedFile::fake()->image('b.png', 900, 900)->store('photos', 'local')]);

    expect(Storage::disk('local')->exists($ancienne))->toBeFalse();
});

it('journalise un changement de statut avec son auteur', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);
    $membre = Membre::factory()->create();

    app(StatutMembreService::class)->changer($membre, StatutMembre::Rejete, $admin, 'Dossier incomplet');

    $ligne = JournalActivite::query()
        ->where('sujet_id', $membre->id)
        ->where('evenement', 'modifie')
        ->latest('id')
        ->first();

    expect($ligne)->not->toBeNull()
        ->and($ligne->user_id)->toBe($admin->id)
        ->and($ligne->changements['apres']['statut'])->toBe('rejete')
        ->and($ligne->changements['avant']['statut'])->toBe('en_attente');
});
