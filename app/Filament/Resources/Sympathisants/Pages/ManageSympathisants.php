<?php

namespace App\Filament\Resources\Sympathisants\Pages;

use App\Filament\Resources\Sympathisants\SympathisantResource;
use Filament\Resources\Pages\ManageRecords;

/** Les sympathisants s'enregistrent au pointage, avec leur accord ; ici, on les suit et on les invite. */
class ManageSympathisants extends ManageRecords
{
    protected static string $resource = SympathisantResource::class;

    public function getSubheading(): ?string
    {
        return 'Les sympathisants s\'enregistrent pendant le pointage d\'une dahira, avec leur accord. Ceux qui ne reviennent pas pendant 2 ans sont effacés automatiquement.';
    }
}
