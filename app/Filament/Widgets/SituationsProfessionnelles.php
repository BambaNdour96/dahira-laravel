<?php

namespace App\Filament\Widgets;

class SituationsProfessionnelles extends RepartitionWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 8;

    protected function titre(): string
    {
        return 'Situations professionnelles';
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function repartition(): array
    {
        return $this->membresValides()
            ->countBy(fn ($membre) => $membre->situation_professionnelle?->getLabel() ?? 'Non renseigné')
            ->all();
    }
}
