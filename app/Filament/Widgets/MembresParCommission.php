<?php

namespace App\Filament\Widgets;

use App\Models\Commission;

class MembresParCommission extends RepartitionWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 4;

    protected function titre(): string
    {
        return 'Membres par commission';
    }

    protected function repartition(): array
    {
        $ids = $this->membresValides()->modelKeys();

        return Commission::query()
            ->orderBy('nom')
            ->get()
            ->mapWithKeys(fn (Commission $commission): array => [
                $commission->nom => $commission->membres()->whereIn('membres.id', $ids)->count(),
            ])
            ->all();
    }
}
