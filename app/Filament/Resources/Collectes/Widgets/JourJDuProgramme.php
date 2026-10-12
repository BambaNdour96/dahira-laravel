<?php

namespace App\Filament\Resources\Collectes\Widgets;

use App\Models\Collecte;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Le volet « jour J » d'un programme : date, lieu, présents et taux de présence. */
class JourJDuProgramme extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = '📍 Le jour J';

    /** @return array<Stat> */
    protected function getStats(): array
    {
        if (! $this->record instanceof Collecte) {
            return [];
        }

        $evenement = $this->record->evenement;

        if ($evenement === null) {
            return [
                Stat::make('Jour J', 'Aucun')
                    ->description('Programme sans rendez-vous. Ajoutez-en un dans « Paramètres » si besoin.')
                    ->color('gray'),
            ];
        }

        $presents = $evenement->presences()->count();
        $concernes = $evenement->membresConcernes();
        $taux = $concernes > 0 ? (int) round($presents / $concernes * 100) : 0;
        $aVenir = $evenement->debut->isFuture();

        return [
            Stat::make('Date', $evenement->debut->translatedFormat('j F Y'))
                ->description($evenement->debut->format('H:i').' · '.($aVenir ? 'dans '.(int) now()->diffInDays($evenement->debut).' jour(s)' : 'passé'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color($aVenir ? 'info' : 'gray'),
            Stat::make('Lieu', $evenement->lieu)
                ->description('Les contrôleurs se choisissent dans « Événements »')
                ->descriptionIcon('heroicon-m-map-pin'),
            Stat::make('Présents', (string) $presents)
                ->description('sur '.$concernes.' membre(s) concerné(s)')
                ->descriptionIcon('heroicon-m-qr-code')
                ->color('primary'),
            Stat::make('Taux de présence', $taux.' %')
                ->color($taux >= 75 ? 'success' : ($taux >= 40 ? 'warning' : 'gray')),
        ];
    }
}
