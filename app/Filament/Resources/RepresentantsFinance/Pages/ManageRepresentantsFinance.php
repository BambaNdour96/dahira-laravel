<?php

namespace App\Filament\Resources\RepresentantsFinance\Pages;

use App\Filament\Resources\RepresentantsFinance\RepresentantFinanceResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRepresentantsFinance extends ManageRecords
{
    protected static string $resource = RepresentantFinanceResource::class;

    public function getSubheading(): ?string
    {
        return 'Un Goor Yalla et une Sokhna par cellule. Ils valident les paiements et encaissent les espèces de leur périmètre ; vous recevez leurs remises.';
    }
}
