<?php

namespace Database\Seeders;

use App\Support\Capacites;
use Illuminate\Database\Seeder;

/**
 * Les casquettes et leurs capacités, d'après la matrice des droits (App\Support\Capacites).
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        Capacites::synchroniser();
    }
}
