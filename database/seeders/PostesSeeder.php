<?php

namespace Database\Seeders;

use App\Models\Poste;
use Illuminate\Database\Seeder;

/**
 * Les postes de la Daara, avec la casquette qu'ils donnent (voir App\Support\Capacites).
 * « Membre de Suivi et Évaluation » est provisoire : il devient une commission au lot R1b.
 */
class PostesSeeder extends Seeder
{
    public const POSTES = [
        ['Jewrine national', 'jewrine_national', 'national', true],
        ['Secrétaire général', 'secretaire_general', 'national', true],
        ['Jewrine finance national', 'jewrine_finance', 'national', true],
        ['Jewrine de cellule', 'jewrine_cellule', 'cellule', true],
        ['Membre de Suivi et Évaluation', 'suivi_evaluation', 'national', false],
        ['Jewrine du comité de suivi des dépenses', 'jewrine_suivi_depenses', 'comite', true],
    ];

    public function run(): void
    {
        foreach (self::POSTES as $ordre => [$nom, $code, $perimetre, $unique]) {
            $poste = Poste::query()->firstOrNew(['code' => $code]);
            $poste->forceFill([
                'nom' => $poste->exists ? $poste->nom : $nom,
                'perimetre' => $perimetre,
                'titulaire_unique' => $unique,
                'ordre' => $ordre + 1,
            ])->save();
        }
    }
}
