<?php

namespace App\Filament\Resources\Caisses\Pages;

use App\Filament\Resources\Caisses\CaisseResource;
use App\Filament\Resources\Caisses\Widgets\MouvementsDeLaCaisse;
use App\Models\Caisse;
use Filament\Resources\Pages\ViewRecord;

/** Une caisse : son solde et tous ses mouvements. */
class ViewCaisse extends ViewRecord
{
    protected static string $resource = CaisseResource::class;

    public function getSubheading(): ?string
    {
        $caisse = $this->getRecord();

        return $caisse instanceof Caisse ? 'Solde : '.number_format($caisse->solde(), 0, ',', ' ').' FCFA' : null;
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [MouvementsDeLaCaisse::class];
    }
}
