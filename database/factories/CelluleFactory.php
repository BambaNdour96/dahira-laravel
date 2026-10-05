<?php

namespace Database\Factories;

use App\Models\Cellule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cellule>
 */
class CelluleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'nom' => fake()->unique()->city(),
            'ville' => fake()->city(),
            'pays' => 'Sénégal',
        ];
    }
}
