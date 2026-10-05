<?php

namespace Database\Factories;

use App\Enums\Sexe;
use App\Models\Cellule;
use App\Models\Membre;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membre>
 */
class MembreFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'prenom' => fake()->firstName(),
            'nom' => fake()->lastName(),
            'sexe' => fake()->randomElement(Sexe::cases()),
            'date_naissance' => fake()->dateTimeBetween('-70 years', '-16 years'),
            'telephone' => '+2217'.fake()->unique()->numerify('########'),
            'cellule_id' => Cellule::factory(),
            'consentement_le' => now(),
            'consentement_version' => '2026-10',
        ];
    }
}
