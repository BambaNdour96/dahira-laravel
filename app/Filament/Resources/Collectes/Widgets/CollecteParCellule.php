<?php

namespace App\Filament\Resources\Collectes\Widgets;

use App\Models\Collecte;
use App\Models\Engagement;
use App\Services\CollecteService;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

/** Engagé et versé, cellule par cellule. */
class CollecteParCellule extends ChartWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    public function getHeading(): string
    {
        return 'Avancement par cellule (FCFA)';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        if (! $this->record instanceof Collecte) {
            return ['datasets' => [], 'labels' => []];
        }

        // L'engagé suit la cellule actuelle du membre ; le versé, la cellule du moment de chaque versement.
        $verses = app(CollecteService::class)->versesParCellule($this->record);
        $engages = $this->record->engagements()->with('membre.cellule')->get()
            ->groupBy(fn (Engagement $engagement): string => $engagement->membre->cellule->nom)
            ->map(fn ($groupe): int => (int) $groupe->sum('montant'));
        $parCellule = collect(array_unique([...$engages->keys()->all(), ...array_keys($verses)]))
            ->mapWithKeys(fn (string $nom): array => [$nom => ['engage' => (int) ($engages[$nom] ?? 0), 'verse' => $verses[$nom] ?? 0]])
            ->sortKeys();

        return [
            'datasets' => [
                ['label' => 'Engagé', 'data' => $parCellule->pluck('engage')->values()->all(), 'backgroundColor' => '#f5e0a0', 'borderColor' => '#d4a017'],
                ['label' => 'Versé', 'data' => $parCellule->pluck('verse')->values()->all(), 'backgroundColor' => '#1e5223', 'borderColor' => '#1e5223'],
            ],
            'labels' => $parCellule->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
