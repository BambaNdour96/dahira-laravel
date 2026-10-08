<?php

namespace App\Filament\Widgets;

use App\Enums\StatutMembre;
use App\Filament\Resources\Membres\MembreResource;
use App\Models\Membre;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Collection;

/**
 * Graphique de répartition des membres validés,
 * limité aux cellules visibles par l'utilisateur connecté.
 */
abstract class RepartitionWidget extends ChartWidget
{
    protected ?string $pollingInterval = null;

    /** Vert et or du logo. */
    protected const COULEURS = ['#1e5223', '#d4a017', '#36923d', '#b3850f', '#8bc98e', '#f5e0a0', '#236428', '#58ad5e'];

    abstract protected function titre(): string;

    /** @return array<int|string, int> */
    abstract protected function repartition(): array;

    public function getHeading(): string
    {
        return $this->titre();
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $repartition = $this->repartition();
        $couleurs = array_merge(self::COULEURS, self::COULEURS, self::COULEURS);

        return [
            'datasets' => [[
                'label' => 'Membres',
                'data' => array_values($repartition),
                'backgroundColor' => array_slice($couleurs, 0, max(count($repartition), 1)),
                'borderColor' => '#1e5223',
            ]],
            'labels' => array_keys($repartition),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array<string, mixed> */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => $this->getType() === 'doughnut']],
        ];
    }

    /**
     * Membres validés visibles par l'utilisateur : tous pour l'administrateur
     * et le Jewrine finance, ceux de sa cellule pour un Jewrine.
     *
     * @return Collection<int, Membre>
     */
    protected function membresValides(): Collection
    {
        return Membre::query()
            ->whereIn('id', MembreResource::getEloquentQuery()->select('membres.id'))
            ->where('statut', StatutMembre::Valide->value)
            ->get();
    }
}
