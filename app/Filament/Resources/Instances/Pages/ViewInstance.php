<?php

namespace App\Filament\Resources\Instances\Pages;

use App\Filament\Resources\Instances\InstanceResource;
use App\Filament\Resources\Instances\Widgets\AvancementCommissions;
use App\Filament\Resources\Instances\Widgets\BudgetCommissions;
use App\Filament\Resources\Instances\Widgets\MembresInstance;
use App\Filament\Resources\Instances\Widgets\ReunionsInstance;
use App\Filament\Resources\Instances\Widgets\TableauDeBordComite;
use App\Filament\Resources\Reunions\ReunionResource;
use App\Models\Instance;
use App\Models\User;
use App\Services\ComiteService;
use DomainException;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

/** Un comité : ses membres, ses commissions et ses réunions. */
class ViewInstance extends ViewRecord
{
    protected static string $resource = InstanceResource::class;

    private static function utilisateur(): ?User
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User ? $utilisateur : null;
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reunion')
                ->label('Nouvelle réunion')
                ->icon('heroicon-o-calendar-days')
                ->visible(fn (Instance $record): bool => ($utilisateur = self::utilisateur()) !== null && ComiteService::peutOrganiser($utilisateur, $record))
                ->url(fn (Instance $record): string => ReunionResource::getUrl('create', ['instance' => $record->id])),
            Action::make('reprendre')
                ->label('Reprendre les commissions précédentes')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (Instance $record): bool => ! $record->estComiteDirecteur() && $record->commissions()->doesntExist()
                    && ($utilisateur = self::utilisateur()) !== null && ComiteService::peutGerer($utilisateur, $record))
                ->requiresConfirmation()
                ->modalDescription('Les commissions du comité précédent du même type sont recopiées, sans leurs chefs : vous les désignerez ensuite.')
                ->action(function (Instance $record): void {
                    try {
                        $nombre = app(ComiteService::class)->reprendreCommissions($record);
                    } catch (DomainException $e) {
                        Notification::make()->title($e->getMessage())->warning()->send();

                        return;
                    }

                    Notification::make()->title($nombre.' commission(s) reprise(s). Désignez maintenant leurs chefs.')->success()->send();
                }),
            Action::make('bilan')
                ->label('Bilan (PDF)')
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->visible(fn (Instance $record): bool => ! $record->estComiteDirecteur())
                ->url(fn (Instance $record): string => route('comites.bilan', $record)),
            EditAction::make(),
        ];
    }

    /** @return array<int, class-string> */
    protected function getHeaderWidgets(): array
    {
        return [TableauDeBordComite::class];
    }

    /** @return array<int, class-string> */
    protected function getFooterWidgets(): array
    {
        return [AvancementCommissions::class, BudgetCommissions::class, ReunionsInstance::class, MembresInstance::class];
    }
}
