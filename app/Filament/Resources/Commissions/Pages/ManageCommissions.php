<?php

namespace App\Filament\Resources\Commissions\Pages;

use App\Filament\Resources\Commissions\CommissionResource;
use App\Models\Commission;
use App\Services\CommissionService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCommissions extends ManageRecords
{
    protected static string $resource = CommissionResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        // Chaque nouvelle commission reçoit aussitôt son espace (réunions, PV, tâches).
        return [CreateAction::make()->after(fn (Commission $record) => app(CommissionService::class)->comite($record))];
    }
}
