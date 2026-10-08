<?php

namespace App\Filament\Widgets;

use Carbon\CarbonInterface;

class TranchesAge extends RepartitionWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 6;

    protected function titre(): string
    {
        return 'Tranches d\'âge';
    }

    protected function repartition(): array
    {
        $tranches = [
            'Moins de 18 ans' => 0,
            '18 à 25 ans' => 0,
            '26 à 35 ans' => 0,
            '36 à 50 ans' => 0,
            'Plus de 50 ans' => 0,
            'Non renseigné' => 0,
        ];

        foreach ($this->membresValides() as $membre) {
            $naissance = $membre->getAttribute('date_naissance');
            $age = $naissance instanceof CarbonInterface ? (int) $naissance->age : null;

            $tranche = match (true) {
                $age === null => 'Non renseigné',
                $age < 18 => 'Moins de 18 ans',
                $age <= 25 => '18 à 25 ans',
                $age <= 35 => '26 à 35 ans',
                $age <= 50 => '36 à 50 ans',
                default => 'Plus de 50 ans',
            };

            $tranches[$tranche]++;
        }

        return $tranches;
    }
}
