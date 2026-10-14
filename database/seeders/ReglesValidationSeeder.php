<?php

namespace Database\Seeders;

use App\Models\Poste;
use App\Models\RegleValidation;
use Illuminate\Database\Seeder;

/**
 * Validation croisée des paiements du bureau :
 * Jewrine finance → Secrétaire général ou Jewrine national ;
 * Secrétaire général → Jewrine national ; Jewrine national → Jewrine finance.
 */
class ReglesValidationSeeder extends Seeder
{
    public function run(): void
    {
        $regles = [
            'jewrine_finance' => ['secretaire_general', 'jewrine_national'],
            'secretaire_general' => ['jewrine_national'],
            'jewrine_national' => ['jewrine_finance'],
        ];

        foreach ($regles as $poste => $validateurs) {
            $posteId = self::idPoste($poste);
            $ids = array_values(array_filter(array_map(fn (string $code): ?int => self::idPoste($code), $validateurs)));

            if ($posteId === null || $ids === []) {
                continue;
            }

            RegleValidation::query()->firstOrCreate(['poste_id' => $posteId], ['validateurs' => $ids]);
        }
    }

    private static function idPoste(string $code): ?int
    {
        $id = Poste::query()->where('code', $code)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
}
