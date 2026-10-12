<?php

namespace App\Filament\Resources\Collectes\Widgets;

use App\Enums\MoyenPaiement;
use App\Enums\StatutPaiement;
use App\Models\Collecte;
use App\Services\CollecteService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** L'analyse d'un programme : participation, engagement moyen, versements, Wave ou espèces. */
class CollecteAnalyse extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Analyse';

    /** @return array<Stat> */
    protected function getStats(): array
    {
        if (! $this->record instanceof Collecte) {
            return [];
        }

        $collecte = $this->record;
        $chiffres = app(CollecteService::class)->statistiques($collecte);
        $participation = $chiffres['concernes'] > 0 ? (int) round($chiffres['engages'] / $chiffres['concernes'] * 100) : 0;
        $moyenne = $chiffres['engages'] > 0 ? intdiv($chiffres['total_engage'], $chiffres['engages']) : 0;

        $confirmes = $collecte->paiements()->where('statut', StatutPaiement::Confirme->value);
        $nombre = (clone $confirmes)->count();
        $parWave = (int) (clone $confirmes)->where('moyen', MoyenPaiement::Wave->value)->sum('montant');
        $partWave = $chiffres['total_verse'] > 0 ? (int) round($parWave / $chiffres['total_verse'] * 100) : 0;
        $enVerification = (int) $collecte->paiements()->where('statut', StatutPaiement::Declare->value)->sum('montant');

        return [
            Stat::make('Taux de participation', $participation.' %')
                ->description($chiffres['engages'].' engagé(s) sur '.$chiffres['concernes'].' concerné(s)')
                ->color($participation >= 75 ? 'success' : ($participation >= 40 ? 'warning' : 'danger')),
            Stat::make('Engagement moyen', CollecteService::fcfa($moyenne))
                ->description('par membre engagé'),
            Stat::make('Versements confirmés', (string) $nombre)
                ->description(CollecteService::fcfa($enVerification).' en vérification'),
            Stat::make('Wave / espèces', $partWave.' % / '.(100 - $partWave).' %')
                ->description('part des montants confirmés'),
        ];
    }
}
