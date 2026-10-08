<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Membres\MembreResource;
use Illuminate\Support\Carbon;

class InscriptionsParMois extends RepartitionWidget
{
    protected ?string  = null;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function titre(): string
    {
        return 'Inscriptions des 12 derniers mois';
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function repartition(): array
    {
        $debut = now()->subMonths(11)->startOfMonth();
        $mois = [];

        for ($i = 0; $i < 12; $i++) {
            $date = $debut->copy()->addMonths($i);
            $mois[$date->format('Y-m')] = ['libelle' => $date->translatedFormat('M Y'), 'nombre' => 0];
        }

        $dates = MembreResource::getEloquentQuery()
            ->where('created_at', '>=', $debut)
            ->pluck('created_at');

        foreach ($dates as $date) {
            $cle = Carbon::parse((string) $date)->format('Y-m');

            if (isset($mois[$cle])) {
                $mois[$cle]['nombre']++;
            }
        }

        return collect($mois)->mapWithKeys(fn (array $m): array => [$m['libelle'] => $m['nombre']])->all();
    }
}
