<?php

namespace App\Filament\Widgets;

use App\Models\Cellule;
use App\Models\Evenement;
use App\Models\User;
use App\Services\CasquettesService;
use App\Services\PointageService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

/** Le taux de présence aux 10 dernières séances de chaque cellule. */
class FrequentationCellules extends ChartWidget
{
    protected static ?int $sort = 6;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User
            && app(CasquettesService::class)->peutQuelquePart($utilisateur, 'dahiras.voir')
            && Evenement::query()->where('type', Evenement::SEANCE)->exists();
    }

    public function getHeading(): string
    {
        return 'Présence aux séances de cellule (%)';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $pointage = app(PointageService::class);
        $couleurs = ['#1e5223', '#d4a017', '#58ad5e', '#0369a1'];
        $datasets = [];
        $libelles = [];

        foreach (Cellule::query()->enActivite()->orderBy('nom')->get() as $index => $cellule) {
            $seances = Evenement::query()
                ->where('type', Evenement::SEANCE)
                ->maintenus()
                ->where('cellule_id', $cellule->id)
                ->where('debut', '<', now())
                ->orderByDesc('debut')
                ->limit(10)
                ->get()
                ->reverse()
                ->values();

            if ($seances->isEmpty()) {
                continue;
            }

            $libelles = count($libelles) >= $seances->count() ? $libelles : $seances->map(fn (Evenement $seance): string => $seance->debut->format('d/m'))->all();
            $couleur = $couleurs[$index % count($couleurs)];

            $datasets[] = [
                'label' => $cellule->nom,
                'data' => $seances->map(fn (Evenement $seance): int => $pointage->bilan($seance)['taux'])->all(),
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
