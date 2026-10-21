<?php

namespace App\Filament\Resources\Instances\Widgets;

use App\Models\Instance;
use App\Services\CollecteService;
use App\Services\SuiviDepensesService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Les indicateurs d'un comité d'organisation : promis, encaissé, en vérification, décaissé, disponible réel, enveloppe. */
class IndicateursComite extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = '💰 L\'argent de l\'événement';

    public static function canView(): bool
    {
        return true;
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        if (! $this->record instanceof Instance || ! $this->record->estComiteOrganisation() || $this->record->collecte === null) {
            return [];
        }

        $chiffres = app(SuiviDepensesService::class)->indicateurs($this->record);
        $fcfa = fn (int $montant): string => CollecteService::fcfa($montant);

        return [
            Stat::make('Promis', $fcfa($chiffres['promis']))->description('Jàyante et Sass des membres'),
            Stat::make('Encaissé', $fcfa($chiffres['encaisse']))->description('Validé par la Finance')->color('success'),
            Stat::make('En vérification', $fcfa($chiffres['verification']))->description('Déclaré, à valider')->color('warning'),
            Stat::make('Décaissé', $fcfa($chiffres['decaisse']))->description('Ordres de dépense payés'),
            Stat::make('Disponible réel', $fcfa($chiffres['disponible']))->description('Encaissé moins décaissé')
                ->color($chiffres['disponible'] < 0 ? 'danger' : 'primary'),
            Stat::make('Enveloppe', $chiffres['enveloppe'] !== null ? $fcfa($chiffres['enveloppe']) : 'Non fixée')
                ->description('Réparti : '.$fcfa($chiffres['reparti']).($chiffres['a_repartir'] !== null ? ' · reste '.$fcfa($chiffres['a_repartir']) : ''))
                ->color($chiffres['a_repartir'] !== null && $chiffres['a_repartir'] < 0 ? 'danger' : 'gray'),
        ];
    }
}
