<?php

use App\Models\Cellule;
use App\Models\Membre;
use App\Models\Parametre;
use App\Models\User;
use Database\Seeders\CellulesSeeder;
use Database\Seeders\ParametresSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, ParametresSeeder::class]);

    Parametre::query()->where('cle', 'whatsapp_national')->update(['valeur' => 'https://chat.whatsapp.com/NATIONAL']);
    $this->cellule = Cellule::factory()->create(['whatsapp_lien' => 'https://chat.whatsapp.com/CELLULE']);

    $this->user = User::factory()->create();
    $this->user->assignRole('membre');
});

it('montre les deux groupes WhatsApp à un membre validé', function () {
    Membre::factory()->for($this->user)->for($this->cellule)->valide()->create();

    $this->actingAs($this->user)->get('/mon-espace')
        ->assertSee('https://chat.whatsapp.com/CELLULE')
        ->assertSee('https://chat.whatsapp.com/NATIONAL');
});

it('cache les groupes WhatsApp tant que la demande est en attente', function () {
    Membre::factory()->for($this->user)->for($this->cellule)->create();

    $this->actingAs($this->user)->get('/mon-espace')
        ->assertDontSee('chat.whatsapp.com');
});

it('réserve les réglages à l\'administrateur', function () {
    $admin = User::factory()->create();
    $admin->assignRole(['super_administrateur', 'administrateur']);

    $this->actingAs($admin)->get('/admin/parametres')->assertOk();
    $this->actingAs($this->user)->get('/admin/parametres')->assertForbidden();
});

it('crée les deux cellules officielles', function () {
    $this->seed(CellulesSeeder::class);

    expect(Cellule::query()->whereIn('nom', ['Dakar', 'Kaolack'])->count())->toBe(2);
});
