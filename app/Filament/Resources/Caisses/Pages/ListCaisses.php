<?php

namespace App\Filament\Resources\Caisses\Pages;

use App\Filament\Resources\Caisses\CaisseResource;
use App\Models\Caisse;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCaisses extends ListRecords
{
    protected static string $resource = CaisseResource::class;

    public function getSubheading(): ?string
    {
        $total = (int) Caisse::query()->enActivite()->get()->sum(fn (Caisse $caisse): int => $caisse->solde());

        return 'Trésorerie : '.number_format($total, 0, ',', ' ').' FCFA dans les caisses en activité.';
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
