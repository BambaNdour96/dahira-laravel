<?php

namespace Database\Factories;

use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
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
