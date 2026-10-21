<?php

namespace App\Filament\Resources\Collectes\Widgets;

use App\Models\Collecte;
use App\Services\CollecteService;
use App\Services\ProgrammeService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

/** Le bilan d'un événement : contribution, objectif atteint ou non, dépenses face au budget, solde, et chaque commission. */
class BilanDuProgramme extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = '📊 Objectif, budget et solde';

    /** @return array<Stat> */
    protected function getStats(): array
    {
        if (! $this->record instanceof Collecte) {
            return [];
        }

        $collecte = $this->record;
        $bilan = app(ProgrammeService::class)->bilan($collecte);
        $fcfa = fn (?int $montant): string => $montant === null ? '—' : CollecteService::fcfa($montant);

        $stats = [
            Stat::make('Contribution', $collecte->mode?->getLabel() ?? 'À choisir')
                ->description(match (true) {
                    $collecte->mode === null => 'Le super administrateur choisit Jàyante ou Sass',
                    $collecte->estSass() => $collecte->montant !== null ? 'Sass de '.$fcfa($collecte->montant).' pour chaque membre' : 'Sass à fixer par le Jewrine national',
                    default => 'Chaque membre fixe son montant',
                })
                ->color($collecte->contributionReglee() ? 'success' : 'warning'),
            Stat::make('Encaissé', $fcfa($bilan['encaisse']))
                ->description($bilan['objectif'] !== null ? 'Objectif '.$fcfa($bilan['objectif']).' · '.$bilan['taux'].' % · '.($bilan['atteint'] ? 'atteint' : 'pas encore atteint') : 'Pas d\'objectif fixé')
                ->color($bilan['atteint'] === true ? 'success' : 'warning'),
            Stat::make('Dépensé', $fcfa($bilan['depense']))
                ->description($bilan['budget'] !== null ? 'Budget '.$fcfa($bilan['budget']).($bilan['depasse'] ? ' · dépassé' : ' · reste '.$fcfa($bilan['budget'] - $bilan['depense'])) : 'Pas de budget fixé')
                ->color($bilan['depasse'] === true ? 'danger' : 'success'),
            Stat::make('Solde', $fcfa($bilan['solde']))
                ->description($bilan['solde_reaffecte'] !== null ? 'Vers la caisse générale : '.$bilan['solde_reaffecte'] : 'Rejoint la caisse générale à la clôture')
                ->color('primary'),
        ];

        foreach ($bilan['commissions'] as $commission) {
            $stats[] = Stat::make('Commission '.$commission['nom'], $fcfa($commission['depense']))
                ->description($commission['budget'] !== null ? 'dépensé sur '.$fcfa($commission['budget']) : 'dépensé, sans part de budget')
                ->color($commission['budget'] !== null && $commission['depense'] > $commission['budget'] ? 'danger' : 'gray');
        }

        return $stats;
    }
}
