<?php

namespace App\Filament\Resources\SessionsActives\Pages;

use App\Filament\Resources\SessionsActives\SessionActiveResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSessionsActives extends ManageRecords
{
    protected static string $resource = SessionActiveResource::class;
}
