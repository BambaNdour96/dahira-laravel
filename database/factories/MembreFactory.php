<?php

namespace Database\Factories;

use App\Enums\NiveauArabe;
use App\Enums\NiveauEtudes;
use App\Enums\Sexe;
use App\Enums\SituationProfessionnelle;
use App\Enums\StatutMembre;
use App\Models\Cellule;
use App\Models\Membre;
use App\Support\Langues;
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
            'adresse' => fake()->streetAddress(),
            'ville' => fake()->randomElement(['Kaolack', 'Dakar', 'Touba', 'Thiès']),
            'cellule_id' => Cellule::factory(),
            'niveau_etudes' => fake()->randomElement(NiveauEtudes::cases()),
            'niveau_arabe' => fake()->randomElement(NiveauArabe::cases()),
            'langues' => fake()->randomElements(array_keys(Langues::liste()), 2),
            'situation_professionnelle' => fake()->randomElement(SituationProfessionnelle::cases()),
            'profession' => fake()->jobTitle(),
            'consentement_le' => now(),
            'consentement_version' => '2026-10',
        ];
    }

    /** Membre déjà validé par le bureau. */
    public function valide(): static
    {
        return $this->state(fn (): array => ['statut' => StatutMembre::Valide]);
    }
}
