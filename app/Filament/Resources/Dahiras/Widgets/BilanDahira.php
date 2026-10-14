<?php

namespace App\Filament\Resources\Dahiras\Widgets;

use App\Models\Evenement;
use App\Services\PointageService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class BilanDahira extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    /** @return array<Stat> */
    protected function getStats(): array
    {
        if (! $this->record instanceof Evenement) {
            return [];
        }

        $bilan = app(PointageService::class)->bilan($this->record);

        return [
            Stat::make('Présents', $bilan['presents'].' / '.$bilan['concernes'])
                ->description($bilan['taux'].' % de présence')
                ->color($bilan['taux'] >= 75 ? 'success' : ($bilan['taux'] >= 40 ? 'warning' : 'danger')),
            Stat::make('Goor Yalla', (string) $bilan['freres']),
            Stat::make('Sokhnas', (string) $bilan['soeurs']),
            Stat::make('Excusés', (string) $bilan['excuses'])->color('warning'),
            Stat::make('Absents', (string) $bilan['absents'])->color($bilan['absents'] > 0 ? 'danger' : 'gray'),
            Stat::make('Fréquentation totale', (string) $bilan['total'])
                ->description($bilan['presents'].' membre(s), '.$bilan['sympathisants'].' sympathisant(s), '.$bilan['visiteurs'].' visiteur(s)'),
        ];
    }
}
