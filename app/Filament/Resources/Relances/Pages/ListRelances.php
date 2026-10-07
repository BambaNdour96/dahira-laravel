<?php

namespace App\Filament\Resources\Relances\Pages;

use App\Filament\Resources\Relances\RelanceResource;
use App\Support\MessagesRelance;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListRelances extends ListRecords
{
    protected static string $resource = RelanceResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('groupe')
                ->label('Rappel dans le groupe WhatsApp')
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->url(fn (): string => 'https://wa.me/?text='.rawurlencode(MessagesRelance::groupe()))
                ->openUrlInNewTab(),
        ];
    }
}
