<?php

use App\Enums\StatutMembre;
use App\Models\Annonce;
use App\Models\Membre;
use App\Models\User;
use App\Notifications\NotificationMembre;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function membreAvecStatut(StatutMembre $statut = StatutMembre::Valide): User
{
    $compte = User::factory()->create();
    $compte->assignRole('membre');
    Membre::factory()->for($compte)->create(['statut' => $statut]);

    return $compte;
}

function annoncesRecues(User $compte): int
{
    return DB::table('notifications')->where('notifiable_id', $compte->id)->where('type', NotificationMembre::class)->count();
}

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->super = User::factory()->create();
    $this->super->assignRole(['super_administrateur', 'administrateur']);
});

it('prévient tous les membres validés d\'une nouvelle annonce, et eux seuls', function () {
    $premier = membreAvecStatut();
    $second = membreAvecStatut();
    $enAttente = membreAvecStatut(StatutMembre::EnAttente);

    $this->actingAs($this->super);
    Annonce::query()->create(['titre' => 'Magal 2026', 'contenu' => 'Départ des cars à 6 h.']);

    expect(annoncesRecues($premier))->toBe(1)
        ->and(annoncesRecues($second))->toBe(1)
        ->and(annoncesRecues($enAttente))->toBe(0);
});

it('affiche l\'affiche et le titre dans le compte des membres', function () {
    $membre = membreAvecStatut();

    $this->actingAs($this->super);
    Annonce::query()->create(['titre' => 'Conférence annuelle', 'affiche_path' => 'affiches/conference.jpg', 'importante' => true]);

    $this->actingAs($membre)->get('/mon-espace')
        ->assertSee('Conférence annuelle')
        ->assertSee('/storage/affiches/conference.jpg', false)
        ->assertSee('Important');
});

it('masque les annonces dont la date de fin est passée', function () {
    $membre = membreAvecStatut();

    $this->actingAs($this->super);
    Annonce::query()->create(['titre' => 'Annonce en cours', 'contenu' => 'Toujours valable.']);
    Annonce::query()->create(['titre' => 'Annonce passée', 'contenu' => 'Terminé.', 'expire_le' => now()->subDay()]);

    $this->actingAs($membre)->get('/annonces')
        ->assertOk()
        ->assertSee('Annonce en cours')
        ->assertDontSee('Annonce passée');
});

it('réserve la publication des annonces au super administrateur', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrateur');

    $jewrine = User::factory()->create();
    $jewrine->assignRole('responsable_section');

    $this->actingAs($this->super)->get('/admin/annonces')->assertOk();
    $this->actingAs($admin)->get('/admin/annonces')->assertForbidden();
    $this->actingAs($jewrine)->get('/admin/annonces')->assertForbidden();

    expect($admin->can('create', Annonce::class))->toBeFalse();
});

it('réserve les annonces aux membres validés', function () {
    $this->actingAs(membreAvecStatut(StatutMembre::EnAttente))->get('/annonces')->assertNotFound();
});
