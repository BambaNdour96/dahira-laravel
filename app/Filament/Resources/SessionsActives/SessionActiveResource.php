<?php

namespace App\Filament\Resources\SessionsActives;

use App\Filament\Resources\SessionsActives\Pages\ManageSessionsActives;
use App\Models\JournalActivite;
use App\Models\SessionActive;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Les personnes connectées en ce moment, réservées au super administrateur.
 */
class SessionActiveResource extends Resource
{
    protected static ?string $model = SessionActive::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'connectes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|UnitEnum|null $navigationGroup = 'Paramètres';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'session';

    protected static ?string $pluralModelLabel = 'connectés en ce moment';

    protected static ?string $navigationLabel = 'Connectés en ce moment';

    public static function canViewAny(): bool
    {
        $utilisateur = Auth::user();

        return $utilisateur instanceof User && $utilisateur->hasRole('super_administrateur');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Sessions de comptes connectés, encore actives dans la plus longue durée d'inactivité.
     *
     * @return Builder<SessionActive>
     */
    public static function requeteActives(): Builder
    {
        $minutes = max(
            (int) config('dahira.inactivite_membres', 6),
            (int) config('dahira.inactivite_bureau', 10),
            (int) config('dahira.inactivite_super_admin', 25),
        );

        return SessionActive::query()
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes($minutes)->getTimestamp());
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) self::requeteActives()->count();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => self::requeteActives()->with('user'))
            ->columns([
                TextColumn::make('user.name')->label('Nom')->searchable(),
                TextColumn::make('user.telephone')->label('Téléphone'),
                TextColumn::make('role')
                    ->label('Rôle')
                    ->state(fn (SessionActive $record): string => $record->user?->getRoleNames()->join(', ') ?: '—'),
                TextColumn::make('appareil')->label('Appareil')->state(fn (SessionActive $record): string => $record->appareil()),
                TextColumn::make('ip_address')->label('Adresse IP')->placeholder('—'),
                TextColumn::make('last_activity')
                    ->label('Dernière activité')
                    ->formatStateUsing(fn ($state): string => Carbon::createFromTimestamp((int) $state)->diffForHumans())
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('deconnecter')
                    ->label('Déconnecter')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('danger')
                    ->hidden(fn (SessionActive $record): bool => $record->id === session()->getId())
                    ->requiresConfirmation()
                    ->modalDescription('La personne sera déconnectée de cet appareil et devra se reconnecter.')
                    ->action(function (SessionActive $record): void {
                        if (! self::canViewAny()) {
                            return;
                        }

                        if ($record->user !== null) {
                            JournalActivite::enregistrer($record->user, 'session_coupee', ['apres' => ['appareil' => $record->appareil()]]);
                        }

                        $record->delete();

                        Notification::make()->title('Session fermée.')->success()->send();
                    }),
            ])
            ->defaultSort('last_activity', 'desc');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return [
            'index' => ManageSessionsActives::route('/'),
        ];
    }
}
