<?php

namespace App\Filament\Widgets;

class NiveauxEtudes extends RepartitionWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 7;

    protected function titre(): string
    {
        return 'Niveaux d\'études';
    }

    protected function repartition(): array
    {
        return $this->membresValides()
            ->countBy(fn ($membre) => $membre->niveau_etudes?->getLabel() ?? 'Non renseigné')
            ->all();
    }
}
