<?php

namespace App\Filament\Resources\Commissions\Pages;

use App\Filament\Resources\Commissions\CommissionResource;
use App\Filament\Resources\Commissions\Widgets\MembresDeLaCommission;
use App\Filament\Resources\Instances\InstanceResource;
use App\Models\Commission;
use App\Services\CommissionService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/** Une commission permanente : son Jewrine, ses membres, les souhaits à traiter, et l'accès à son espace. */
class ViewCommission extends ViewRecord
{
    protected static string $resource = CommissionResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('espace')
                ->label('Espace de la commission (réunions, PV, tâches)')
                ->icon('heroicon-o-users')
                ->url(fn (Commission $record): string => InstanceResource::getUrl('view', ['record' => app(CommissionService::class)->comite($record)])),
        ];
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [MembresDeLaCommission::class];
    }
}
