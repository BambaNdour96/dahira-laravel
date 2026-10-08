<?php

namespace App\Filament\Widgets;

class RepartitionSexe extends RepartitionWidget
{
    protected ?string  = null;

    protected static ?int $sort = 5;

    protected function titre(): string
    {
        return 'Répartition par sexe';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function repartition(): array
    {
        return $this->membresValides()
            ->countBy(fn ($membre) => $membre->sexe->getLabel())
            ->all();
    }
}
