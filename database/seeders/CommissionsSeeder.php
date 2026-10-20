<?php

namespace Database\Seeders;

use App\Models\Commission;
use App\Services\CommissionService;
use Illuminate\Database\Seeder;

/**
 * Les commissions permanentes officielles de la Daara, créées aussi en production, chacune avec son espace.
 */
class CommissionsSeeder extends Seeder
{
    public const COMMISSIONS = ['Administration', 'Suivi et Évaluation', 'Communication', 'Sociale', 'Culturelle', 'Finance', 'Organisation'];

    public function run(CommissionService $commissions): void
    {
        foreach (self::COMMISSIONS as $nom) {
            $commission = Commission::query()->firstOrCreate(['nom' => $nom]);

            if ($nom === 'Suivi et Évaluation' && $commission->code === null) {
                $commission->forceFill(['code' => Commission::SUIVI_EVALUATION])->save();
            }

            if ($nom === 'Culturelle' && $commission->code === null) {
                $commission->forceFill(['code' => Commission::CULTURELLE])->save();
            }

            $commissions->comite($commission);
        }
    }
}
