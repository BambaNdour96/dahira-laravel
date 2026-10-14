<?php

namespace App\Filament\Widgets;

use App\Models\Cellule;
use App\Models\Evenement;
use App\Models\User;
use App\Services\CasquettesService;
use App\Services\PointageService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

/** Le taux de présence aux 10 dernières dahiras de chaque cellule. */
class FrequentationCellules extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User
            && app(CasquettesService::class)->peutQuelquePart($utilisateur, 'dahiras.voir')
            && Evenement::query()->where('type', Evenement::DAHIRA)->exists();
    }

    public function getHeading(): string
    {
        return 'Présence aux dahiras (%)';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $pointage = app(PointageService::class);
        $couleurs = ['#1e5223', '#d4a017', '#58ad5e', '#0369a1'];
        $datasets = [];
        $libelles = [];

        foreach (Cellule::query()->enActivite()->orderBy('nom')->get() as $index => $cellule) {
            $dahiras = Evenement::query()
                ->where('type', Evenement::DAHIRA)
                ->maintenus()
                ->where('cellule_id', $cellule->id)
                ->where('debut', '<', now())
                ->orderByDesc('debut')
                ->limit(10)
                ->get()
                ->reverse()
                ->values();

            if ($dahiras->isEmpty()) {
                continue;
            }

            $libelles = count($libelles) >= $dahiras->count() ? $libelles : $dahiras->map(fn (Evenement $dahira): string => $dahira->debut->format('d/m'))->all();
            $couleur = $couleurs[$index % count($couleurs)];

            $datasets[] = [
                'label' => $cellule->nom,
                'data' => $dahiras->map(fn (Evenement $dahira): int => $pointage->bilan($dahira)['taux'])->all(),
                'borderColor' => $couleur,
                'backgroundColor' => $couleur,
                'tension' => 0.3,
            ];
        }

        return ['datasets' => $datasets, 'labels' => $libelles];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
