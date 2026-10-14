<?php

namespace App\Filament\Resources\BesoinsCommission\Pages;

use App\Filament\Resources\BesoinsCommission\BesoinCommissionResource;
use Filament\Resources\Pages\ListRecords;

class ListBesoinsCommission extends ListRecords
{
    protected static string $resource = BesoinCommissionResource::class;

    public function getSubheading(): ?string
    {
        return 'Les chefs de commission expriment leurs besoins depuis leur téléphone (Mes comités). Le budget d\'une commission est la somme de ses besoins retenus.';
    }
}
