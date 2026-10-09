<?php

use Database\Seeders\CellulesSeeder;
use Database\Seeders\CommissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('propose de prendre la photo avec l\'appareil ou de la choisir dans la galerie', function () {
    $this->seed([CellulesSeeder::class, CommissionsSeeder::class]);

    $this->get('/inscription')
        ->assertOk()
        ->assertSee('Prendre une photo')
        ->assertSee('Choisir une photo')
        ->assertSee('capture="user"', false);
});
