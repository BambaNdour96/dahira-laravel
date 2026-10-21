<?php

namespace App\Filament\Resources\Instances\Widgets;

use App\Models\Instance;
use App\Models\PropositionSuiviDepenses;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Le comité de suivi des dépenses : actif ou non, son Jewrine, la proposition en attente. */
class SuiviDepensesComite extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = '⚖️ Le suivi des dépenses';

    /** @return array<Stat> */
    protected function getStats(): array
    {
        if (! $this->record instanceof Instance || ! $this->record->estComiteOrganisation()) {
            return [];
        }

        if (! $this->record->suivi_depenses) {
            return [Stat::make('Comité de suivi des dépenses', 'Non activé')
                ->description('Le Jewrine national arbitre le budget et signe les ordres de dépense.')->color('gray')];
        }

        $jewrine = $this->record->jewrineSuiviDepenses();
        $proposition = $this->record->propositionsSuivi()->with('membre')->where('statut', PropositionSuiviDepenses::PROPOSEE)->latest()->first();

        return [
            Stat::make('Comité de suivi des dépenses', 'Actif')->description('Il arbitre le budget des commissions et signe les ordres de dépense.')->color('success'),
            Stat::make('Son Jewrine', $jewrine->name ?? 'À nommer')
                ->description($proposition !== null ? 'Proposé : '.$proposition->membre->nom_complet.', à valider par le Jewrine national' : 'Proposé par le président, validé par le Jewrine national')
                ->color($jewrine !== null ? 'success' : 'warning'),
        ];
    }
}
