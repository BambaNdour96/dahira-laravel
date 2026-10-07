<?php

namespace App\Filament\Resources\ComptesBureau\Pages;

use App\Enums\StatutMembre;
use App\Filament\Resources\ComptesBureau\CompteBureauResource;
use App\Models\Membre;
use App\Models\User;
use App\Services\CompteBureauService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;

class ManageComptesBureau extends ManageRecords
{
    protected static string $resource = CompteBureauResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('nommer')
                ->label('Nommer un membre au bureau')
                ->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => Gate::allows('create', User::class))
                ->schema([
                    Select::make('membre_id')
                        ->label('Membre validé')
                        ->options(fn (): array => self::membresNommables())
                        ->searchable()
                        ->required()
                        ->helperText('Seuls les membres validés, qui ne sont pas déjà au bureau, apparaissent ici.'),
                    ...CompteBureauResource::champsRole(),
                ])
                ->action(function (array $data): void {
                    if (! Gate::allows('create', User::class)) {
                        return;
                    }

                    $membre = Membre::query()->whereKey($data['membre_id'])->firstOrFail();

                    try {
                        app(CompteBureauService::class)->nommer($membre, (string) $data['role'], $data['cellule_id'] ?? null);
                    } catch (DomainException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title($membre->prenom.' '.$membre->nom.' a été nommé au bureau.')
                        ->body('Il se connecte avec son numéro et son mot de passe habituels.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /** @return array<int, string> */
    private static function membresNommables(): array
    {
        return Membre::query()
            ->with('cellule')
            ->where('statut', StatutMembre::Valide->value)
            ->whereNotNull('user_id')
            ->whereDoesntHave('user.roles', fn ($roles) => $roles->whereIn('name', CompteBureauService::ROLES_BUREAU))
            ->orderBy('nom')
            ->get()
            ->mapWithKeys(fn (Membre $membre): array => [
                $membre->id => $membre->prenom.' '.$membre->nom.' — '.$membre->matricule.' — '.$membre->cellule->nom,
            ])
            ->all();
    }
}
