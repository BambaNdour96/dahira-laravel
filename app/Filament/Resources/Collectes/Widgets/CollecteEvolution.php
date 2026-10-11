<?php

namespace App\Filament\Resources\Collectes\Widgets;

use App\Enums\StatutPaiement;
use App\Models\Collecte;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

/** La progression jour par jour des versements confirmés, face à l'objectif. */
class CollecteEvolution extends ChartWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = null;

    public function getHeading(): string
    {
        return 'Évolution de la collecte (FCFA)';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        if (! $this->record instanceof Collecte) {
            return ['datasets' => [], 'labels' => []];
        }

        $collecte = $this->record;
        $debut = $collecte->ouverture->copy()->startOfDay();
        $fin = now()->startOfDay();
        $jours = max(1, min(90, (int) $debut->diffInDays($fin) + 1));
        $debut = $fin->copy()->subDays($jours - 1);

        $parJour = $collecte->paiements()
            ->where('statut', StatutPaiement::Confirme->value)
            ->get(['traite_le', 'montant'])
            ->groupBy(fn ($paiement): string => (string) $paiement->traite_le?->format('Y-m-d'))
            ->map(fn ($groupe): int => (int) $groupe->sum('montant'));

        $avant = (int) $collecte->paiements()
            ->where('statut', StatutPaiement::Confirme->value)
            ->where('traite_le', '<', $debut)
            ->sum('montant');

        $libelles = [];
        $cumul = [];
        $total = $avant;

        for ($i = 0; $i < $jours; $i++) {
            $jour = $debut->copy()->addDays($i);
            $total += (int) ($parJour[$jour->format('Y-m-d')] ?? 0);
            $libelles[] = $jour->format('d/m');
            $cumul[] = $total;
        }

        $datasets = [[
            'label' => 'Collecté',
            'data' => $cumul,
            'borderColor' => '#1e5223',
            'backgroundColor' => 'rgba(30, 82, 35, 0.15)',
            'fill' => true,
            'tension' => 0.3,
        ]];

        $cible = $collecte->cible();

        if ($cible > 0) {
            $datasets[] = [
                'label' => $collecte->objectif !== null ? 'Objectif' : 'Total des engagements',
                'data' => array_fill(0, $jours, $cible),
                'borderColor' => '#d4a017',
                'borderDash' => [6, 6],
                'pointRadius' => 0,
                'fill' => false,
            ];
        }

        return ['datasets' => $datasets, 'labels' => $libelles];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
