<?php

namespace App\Filament\Widgets;

class MembresParCellule extends RepartitionWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 3;

    protected function titre(): string
    {
        return 'Membres par cellule';
    }

    protected function repartition(): array
    {
        return $this->membresValides()
            ->load('cellule')
            ->countBy(fn ($membre) => $membre->cellule->nom)
            ->sortKeys()
            ->all();
    }
}
