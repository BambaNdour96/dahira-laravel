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
            'Jewrine finance' => ['Secrétaire général', 'Jewrine national'],
            'Secrétaire général' => ['Jewrine national'],
            'Jewrine national' => ['Jewrine finance'],
        ];

        foreach ($regles as $poste => $validateurs) {
            $posteId = self::idPoste($poste);
            $ids = array_values(array_filter(array_map(fn (string $nom): ?int => self::idPoste($nom), $validateurs)));

            if ($posteId === null || $ids === []) {
                continue;
            }

            RegleValidation::query()->firstOrCreate(['poste_id' => $posteId], ['validateurs' => $ids]);
        }
    }

    private static function idPoste(string $nom): ?int
    {
        $id = Poste::query()->where('nom', $nom)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
}
